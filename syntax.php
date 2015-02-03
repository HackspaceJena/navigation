<?php
/**
 * Plugin Now: Inserts a timestamp.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */

// must be run within DokuWiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once DOKU_PLUGIN . 'syntax.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . '../packagist/vendor/autoload.php';

use enko\dokuwiki\objectrepresentation\DokuWikiIterator;
use enko\dokuwiki\objectrepresentation\DokuWikiNode;
use enko\dokuwiki\objectrepresentation\DokuWikiNameSpace;
use enko\dokuwiki\objectrepresentation\DokuWikiPage;

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_navigation extends DokuWiki_Syntax_Plugin
{

    private $maxDepth = 3;
    private $depth = 0;

    /** @var string A regexp to configure which pages to exclude */
    private $exclusion_mask = '';

    function __construct()
    {
        $this->exclusion_mask = $this->getConf('exclusion_mask');
        $this->maxDepth = $this->getConf('treedepth');
    }

    function getInfo()
    {
        return array('author' => 'Tim Schumacher',
            'email' => 'tim@bandenkrieg.hacked.jp',
            'date' => '2013-11-12',
            'name' => 'Navigation',
            'desc' => 'A Navigation that uses the object representation class',
            'url' => 'https://bk-dev.hacked.jp/project/view/3/');
    }

    function getType()
    {
        return 'substition';
    }

    function getSort()
    {
        return 32;
    }

    function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{indexmenu_n>.+?}}', $mode, 'plugin_navigation');
        $this->Lexer->addSpecialPattern('\[Navigation\]', $mode, 'plugin_navigation');
    }

    function handle($match, $state, $pos, &$handler)
    {
        return array($match, $state, $pos);
    }

    function render($mode, &$renderer, $data)
    {
        if (preg_match('/{{indexmenu_n>(\d+)}}/', $data[0], $matches)) {
            global $ACT, $INFO;
            if ($INFO['isadmin'] && $ACT == 'show') {
                ptln('<div class="info">');
                ptln('Sortorder for this node: ' . $matches[1]);
                ptln('</div>');
            }
            return false;
        }
        global $INFO;

        $iter = new DokuWikiIterator();

        $iter->all(function (DokuWikiNode $node) use($INFO) {
            $node->setMetaData('active',$node->getFullID() == $INFO['id']);
            if (preg_match('/{{indexmenu_n>(\d+)}}/', $node->getContent(), $matches)) {
                $node->setMetaData('sortorder', $matches[1]);
            } else {
                $node->setMetaData('sortorder', 9999999);
            }
        });

        $iter->all(function (DokuWikiNode $node) {
            if ($node instanceof DokuWikiNameSpace) {
                $node->nodes->uasort(function (DokuWikiNode $a, DokuWikiNode $b) {
                    if ($a->getMetaData('sortorder') == $b->getMetaData('sortorder')) {
                        $title_a = (strlen($a->getMetaData('title')) > 0 ? $a->getMetaData('title') : $a->getName());
                        $title_b = (strlen($b->getMetaData('title')) > 0 ? $b->getMetaData('title') : $b->getName());
                        return strnatcasecmp($title_a,$title_b);
                    }
                    return ($a->getMetaData('sortorder') < $b->getMetaData('sortorder')) ? -1 : 1;
                });
            }
        });

        $root = $iter->getRoot();
        $content = '';
        if ($root instanceof DokuWikiNameSpace) {
            $nodes = $root->getNodes();
            if ($nodes->count() > 0) {
                $content .= '<div class="navigation">';
                $content .= '<h3>Navigation</h3>';
                $content .= '<nav class="root">';
                $content .= $this->RenderNodes($root);
                $content .= '</nav>';
                $content .= '</div>';
            }
        }

        // $data is what the function handle return'ed.
        if ($mode == 'xhtml') {
            $renderer->doc .= $content;
            return true;
        }
        return false;
    }

    private function RenderNodes(DokuWikiNameSpace $node)
    {
        global $INFO,$conf;
        $this->depth++;
        $output = '';
        foreach ($node->getNodes() as $node) {
            /** @var DokuWikiNode $node */
            if ($node->getName() == 'start')
                continue;
            if (preg_match($this->exclusion_mask,$node->getFullID()))
                continue;
            // check if the translation plugin is active and if so, hide all the translation namespaces
            if (!plugin_isdisabled('translation')) {
                $translations = $this->getTranslations();
                if (in_array($node->getName(),$translations))
                    continue;
            }
            $title = (strlen($node->getMetaData('title')) > 0 ? $node->getMetaData('title') : $node->getName());
            $access = auth_quickaclcheck($node->getFullID());
            if ($node instanceof DokuWikiPage) {
                if (($access > 0))  {
                    $output .= '<li class="node"><a class="' . ($node->getMetaData('active') ? 'active ' : '') .'" href="' . wl($node->getFullID()) . '"><span class="icon el-icon-file"></span><span class="text">' . $title . '</span></a></li>' . PHP_EOL;
                }

            } else if ($node instanceof DokuWikiNameSpace) {
                /** @var DokuWikiNameSpace $node */
                if (($this->depth <= $this->maxDepth) || (strpos($INFO['id'],$node->getFullID()) === 0)) {
                    if ($access > 0) {
                        // lets check if the the namespace has a startpage and if yes link to it
                        if ($start = $node->hasChild('start')) {
                            $access = auth_quickaclcheck($start->getFullID());
                            if ($access > 0) {
                                $title = '<a class="' . ($start->getMetaData('active') ? 'active ' : '') .'" href="' . wl($start->getFullID()) . '"><span class="icon el-icon-folder-open"></span><span class="text">' . (strlen($start->getMetaData('title')) > 0 ? $start->getMetaData('title') : $start->getName()) . '</span></a>';
                            }
                        }
                        $output .= '<li class="namespace">' . $title . '<nav>' . $this->RenderNodes($node) . '</nav></li>' . PHP_EOL;
                        $this->depth--;
                    }
                } else {
                    // if we have reached the maximum depth, lets at least check if the namespace has a starting page and display this
                    if ($start = $node->hasChild('start')) {
                        $access = auth_quickaclcheck($start->getFullID());
                        if ($access > 0) {
                            $title = (strlen($start->getMetaData('title')) > 0 ? $start->getMetaData('title') : $start->getName());
                            $output .= '<li class="namespace"><a class="' . ($start->getMetaData('active') ? 'active ' : '') .'" href="' . wl($start->getFullID()) . '"><span class="icon el-icon-folder-close"></span><span class="text">' . $title . '</span></a></li>' . PHP_EOL;
                        }
                    }
                }
            }
        }
        return $output;
    }

    /**
     * @param $conf
     * @return array|string
     */
    private function getTranslations()
    {
        global $conf;
        $translations = strtolower(str_replace(',', ' ', $conf['plugin']['translation']['translations']));
        $translations = array_unique(array_filter(explode(' ', $translations)));
        sort($translations);
        return $translations;
    }
}