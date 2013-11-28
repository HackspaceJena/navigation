<?php
/**
 * Plugin Now: Inserts a timestamp.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */

// must be run within DokuWiki
if (!defined ('DOKU_INC')) die();

if (!defined ('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once DOKU_PLUGIN . 'syntax.php';

require_once dirname (__FILE__) . DIRECTORY_SEPARATOR . 'objectrepresentation' . DIRECTORY_SEPARATOR . 'DokuWikiObjectRepresentation.class.php';

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_navigation extends DokuWiki_Syntax_Plugin {

  // TODO add a config option for this
  private $maxDepth = 2;
  private $depth = 0;

  function getInfo () {
    return array ('author' => 'Tim Schumacher',
      'email' => 'tim@bandenkrieg.hacked.jp',
      'date' => '2013-11-12',
      'name' => 'Navigation',
      'desc' => 'A Navigation that uses the object representation class',
      'url' => 'https://bk-dev.hacked.jp/project/view/3/');
  }

  function getType () {
    return 'substition';
  }

  function getSort () {
    return 32;
  }

  function connectTo ($mode) {
    $this->Lexer->addSpecialPattern('{{indexmenu>.+?}}',$mode,'plugin_navigation');
    $this->Lexer->addSpecialPattern ('\[Navigation\]', $mode, 'plugin_navigation');
  }

  function handle ($match, $state, $pos, &$handler) {
    return array ($match, $state, $pos);
  }

  function render ($mode, &$renderer, $data) {
    global $ID;

    $iter = new DokuWikiIterator();

    $iter->all(function(DokuWikiNode $node) {
      if (preg_match('/{{indexmenu_n>(\d+)}}/',$node->getContent(),$matches)) {
        $node->setMetaData('sortorder',$matches[1]);
      } else {
        $node->setMetaData('sortorder',9999999);
      }
    });

    $iter->all(function(DokuWikiNode $node){
      if ($node instanceof DokuWikiNameSpace) {
        $node->nodes->uasort(function(DokuWikiNode $a,DokuWikiNode $b){
          if ($a->getMetaData('sortorder') == $b->getMetaData('sortorder')) {
            return 0;
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
        $content .= '<ul>';
        $content .= $this->RenderNodes($root);
        $content .= '</ul>';
      }
    }

    // $data is what the function handle return'ed.
    if ($mode == 'xhtml') {
      $renderer->doc .= $content;
      return true;
    }
    return false;
  }

  private function RenderNodes(DokuWikiNameSpace $node) {
    $this->depth++;
    $output = '';
    foreach ($node->getNodes() as $node) {
      /** @var DokuWikiNode $node */
      if ($node instanceof DokuWikiPage) {
        $output .= '<li><a href="' . wl($node->getFullID()) . '">' . $node->getName() . '</a></li>';
      } else {
        if ($this->depth <= $this->maxDepth) {
          $output .= '<li>' . $node->getName() . '<ul>' . $this->RenderNodes($node) . '</ul></li>';
        }
      }
    }
    return $output;
  }
}