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

require_once dirname (__FILE__) . DIRECTORY_SEPARATOR . 'DokuWikiObjectRepresentation.class.php';

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_navigation extends DokuWiki_Syntax_Plugin {

  function getInfo () {
    return array ('author' => 'Tim Schumacher',
      'email' => 'me@someplace.com',
      'date' => '2005-07-28',
      'name' => 'Now Plugin',
      'desc' => 'Include the current date and time',
      'url' => 'http://www.dokuwiki.org/devel:syntax_plugins');
  }

  function getType () {
    return 'substition';
  }

  function getSort () {
    return 32;
  }

  function connectTo ($mode) {
    $this->Lexer->addSpecialPattern('{{indexmenu_n>(\d+)}}',$mode,'plugin_navigation');
    $this->Lexer->addSpecialPattern ('\[NOW\]', $mode, 'plugin_navigation');
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

    $content = '<ul>';

    $iter->all(function(DokuWikiNode $node) use (&$content){
      if ($node->getName() != 'root') {
        $content .= '<li>' . $node->getFullID() . ':' . $node->getMetaData('sortorder') . '</li>';
      }
    });

    $content .= '</ul>';

    // $data is what the function handle return'ed.
    if ($mode == 'xhtml') {
      $renderer->doc .= $content;
      return true;
    }
    return false;
  }
}