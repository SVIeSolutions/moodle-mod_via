<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * xml_unserialize: takes raw XML as a parameter (a string)
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

/**
 * XML Library, by Keith Devens, version 1.2b
 * http://keithdevens.com/software/phpxml
 *
 * This code is Open Source, released under terms similar to the Artistic License.
 * Read the license at http://keithdevens.com/software/license
 *
 * xml_unserialize: takes raw XML as a parameter (a string)
 * and returns an equivalent PHP data structure
 */

function & xml_unserialize(&$xml) {
    $xmlparser = new xml();
    $data = $xmlparser->parse($xml);
    $xmlparser->destruct();
    return $data;
}
/**
 * XML_serialize: serializes any PHP data structure into XML
 * Takes one parameter: the data to serialize. Must be an array.
 *
 */

function & xml_serialize(&$data, $level = 0, $priorkey = null) {
    if ($level == 0) {
        ob_start();
        echo '<?xml version="1.0" ?>', "\n";
    }
    while (list($key, $value) = each($data)) {
        if (!strpos($key, ' attr')) {
            // If it's not an attribute we don't treat attributes by themselves,
            // so for an empty element that has attributes you still need to set the element to null.

            if (is_array($value) and array_key_exists(0, $value)) {
                xml_serialize($value, $level, $key);
            } else {
                $tag = $priorkey ? $priorkey : $key;
                echo str_repeat("\t", $level), '<', $tag;
                if (array_key_exists("$key attr", $data)) {// If there's an attribute for this element.
                    while (list($attrname, $attrvalue) = each($data["$key attr"])) {
                        echo ' ', $attrname, '="', htmlspecialchars($attrvalue), '"';
                    }
                    reset($data["$key attr"]);
                }

                if (is_null($value)) {
                    echo " />\n";
                } else if (!is_array($value)) {
                    echo '>', htmlspecialchars($value), "</$tag>\n";
                } else {
                    echo ">\n", xml_serialize($value, $level + 1), str_repeat("\t", $level), "</$tag>\n";
                }
            }
        }
    }
    reset($data);

    if ($level == 0) {
        $str = &ob_get_contents();
        ob_end_clean();
        return $str;
    }
}

/**
 * XML class: utility class to be used with PHP's XML handling functions
 */
class xml{

    private $parser;// A reference to the XML parser.
    private $document; // The entire XML structure built up so far.
    private $parent;// A pointer to the current parent - the parent will be an array.
    private $stack;// A stack of the most recent parent at each nesting level.
    private $lastopenedtag;// Keeps track of the last tag opened.

    public function __construct() {
        $this->parser = xml_parser_create();
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'open', 'close');
        xml_set_character_data_handler($this->parser, 'data');
    }

    public function destruct() {
        xml_parser_free($this->parser);
    }

    public function parse(&$data) {
        $this->document = array();
        $this->stack    = array();
        $this->parent   = &$this->document;
        return xml_parse($this->parser, $data, true) ? $this->document : null;
    }

    public function open(&$parser, $tag, $attributes) {
        $this->data = '';// Stores temporary cdata!
        $this->lastopenedtag = $tag;
        if (is_array($this->parent) and array_key_exists($tag, $this->parent)) {
            if (is_array($this->parent[$tag]) and array_key_exists(0, $this->parent[$tag])) {
                // This is the third or later instance of $tag we've come across.
                $key = count_numeric_items($this->parent[$tag]);
            } else {
                // This is the second instance of $tag that we've seen. shift around.
                if (array_key_exists("$tag attr", $this->parent)) {
                    $arr = array('0 attr' => &$this->parent["$tag attr"], &$this->parent[$tag]);
                    unset($this->parent["$tag attr"]);
                } else {
                    $arr = array(&$this->parent[$tag]);
                }
                $this->parent[$tag] = &$arr;
                $key = 1;
            }
            $this->parent = &$this->parent[$tag];
        } else {
            $key = $tag;
        }
        if ($attributes) {
            $this->parent["$key attr"] = $attributes;
        }
        $this->parent  = &$this->parent[$key];
        $this->stack[] = &$this->parent;
    }

    public function data(&$parser, $data) {
        if ($this->lastopenedtag != null) {
            $this->data .= $data;
        }
    }

    public function close(&$parser, $tag) {
        if ($this->lastopenedtag == $tag) {
            $this->parent = $this->data;
            $this->lastopenedtag = null;
        }
        array_pop($this->stack);
        if ($this->stack) {
            $this->parent = &$this->stack[count($this->stack) - 1];
        }
    }
}

function count_numeric_items(&$array) {
    return is_array($array) ? count(array_filter(array_keys($array), 'is_numeric')) : 0;
}
