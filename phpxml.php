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
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

###################################################################################
#
# XML Library, by Keith Devens, version 1.2b
# http://keithdevens.com/software/phpxml
#
# This code is Open Source, released under terms similar to the Artistic License.
# Read the license at http://keithdevens.com/software/license
#
###################################################################################

###################################################################################
# XML_unserialize: takes raw XML as a parameter (a string)
# and returns an equivalent PHP data structure
###################################################################################
function & XML_unserialize(&$xml) {
    $xml_parser = new XML();
    $data = $xml_parser->parse($xml);
    $xml_parser->destruct();
    return $data;
}
###################################################################################
# XML_serialize: serializes any PHP data structure into XML
# Takes one parameter: the data to serialize. Must be an array.
###################################################################################
function & XML_serialize(&$data, $level = 0, $prior_key = NULL) {
    if ($level == 0) { ob_start(); echo '<?xml version="1.0" ?>',"\n"; }
    while(list($key, $value) = each($data))
    if (!strpos($key, ' attr'))
        // If it's not an attribute we don't treat attributes by themselves,
        // so for an empty element that has attributes you still need to set the element to NULL.

        if (is_array($value) and array_key_exists(0, $value)) {
            XML_serialize($value, $level, $key);
        } else {
            $tag = $prior_key ? $prior_key : $key;
            echo str_repeat("\t", $level),'<',$tag;
            if (array_key_exists("$key attr", $data)) {// If there's an attribute for this element.
                while(list($attr_name, $attr_value) = each($data["$key attr"]))
                echo ' ',$attr_name,'="',htmlspecialchars($attr_value),'"';
                reset($data["$key attr"]);
            }

            if (is_null($value)) echo " />\n";
            elseif (!is_array($value)) echo '>',htmlspecialchars($value),"</$tag>\n";
            else echo ">\n",XML_serialize($value, $level+1),str_repeat("\t", $level),"</$tag>\n";
        }
    reset($data);
    if ($level == 0) { $str = &ob_get_contents(); ob_end_clean(); return $str; }
}
###################################################################################
# XML class: utility class to be used with PHP's XML handling functions
###################################################################################
class XML{
    var $parser;// A reference to the XML parser.
    var $document; // The entire XML structure built up so far.
    var $parent;// A pointer to the current parent - the parent will be an array.
    var $stack;// A stack of the most recent parent at each nesting level.
    var $last_opened_tag;// Keeps track of the last tag opened.

    function XML() {
        $this->parser = xml_parser_create();
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'open', 'close');
        xml_set_character_data_handler($this->parser, 'data');
    }
    function destruct() { xml_parser_free($this->parser); }
    // Changed for Moodle MDL-13631!
    function parse(&$data) {
        $this->document = array();
        $this->stack    = array();
        $this->parent   = &$this->document;
        return xml_parse($this->parser, $data, true) ? $this->document : NULL;
    }
    function open(&$parser, $tag, $attributes) {
        $this->data = '';// Stores temporary cdata!
        $this->last_opened_tag = $tag;
        if (is_array($this->parent) and array_key_exists($tag,$this->parent)) {
            if (is_array($this->parent[$tag]) and array_key_exists(0,$this->parent[$tag])) {
                // This is the third or later instance of $tag we've come across.
                $key = count_numeric_items($this->parent[$tag]);
            } else {
                // This is the second instance of $tag that we've seen. shift around.
                if (array_key_exists("$tag attr",$this->parent)) {
                    $arr = array('0 attr'=>&$this->parent["$tag attr"], &$this->parent[$tag]);
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
        if ($attributes) $this->parent["$key attr"] = $attributes;
        $this->parent  = &$this->parent[$key];
        $this->stack[] = &$this->parent;
    }
    function data(&$parser, $data) {
        if ($this->last_opened_tag != NULL)
            $this->data .= $data;
    }
    function close(&$parser, $tag) {
        if ($this->last_opened_tag == $tag) {
            $this->parent = $this->data;
            $this->last_opened_tag = NULL;
        }
        array_pop($this->stack);
        if ($this->stack) $this->parent = &$this->stack[count($this->stack)-1];
    }
}
function count_numeric_items(&$array) {
    return is_array($array) ? count(array_filter(array_keys($array), 'is_numeric')) : 0;
}
?>
