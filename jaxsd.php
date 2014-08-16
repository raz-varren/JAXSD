<?php

/*
**  This class will convert a JSON Schema object to
**  an XSD Schema document
*/

class Jaxsd_Exception extends \Exception { }

class Jaxsd{
	
	private static $xml = null;
	
	private static $number_restrictions = array(
		'minLength',
		'maxLength',
		'minimum',
		'maximum',
	);
	
	private static $string_restrictions = array(
		'minLength',
		'maxLength',
		'pattern',
	);
	
	private static $jtype2xtype = array(
		'number' => 'integer',
	);
	
	private static $ns;
	
	public static function convert($json, $pretty = false, $root_node = 'root', $ns = 'xs'){
		if(is_string($json)){
			$json = json_decode($json);
		}
		
		if(!is_object($json)) throw new Jaxsd_Exception('Error parsing JSON file');
		
		self::$ns = $ns.':';
		
		self::$xml = new \DOMDocument('1.0', 'UTF-8');
		self::$xml->formatOutput = $pretty;
		
		$schema = self::$xml->createElementNS('http://www.w3.org/2001/XMLSchema', self::nsval('schema'));
		$root = self::elem('element');
		$att = self::attr('name');
		$att->value = $root_node;
		$root->appendChild($att);
		
		$schema->appendChild(self::gen_node($json, $root));
		
		self::$xml->appendChild($schema);
		
		return self::$xml->saveXML();
	}
	
	private static function gen_node($node, $xml){
		
		//print_r($node);
		
		$skip_min = false;
		
		if($node->type == 'array'){
			
			if($node->items->type == 'object'){
				
				$xml->appendChild(self::make_object($node, true));
			}elseif($node->enum){
				
				$xml->appendChild(self::make_enum($node, true));
			}elseif($node->items->type == 'string'){
				
				$xml->appendChild(self::make_string($node, true));
			}elseif($node->items->type == 'number'){
				
				$xml->appendChild(self::make_number($node, true));
			}elseif(is_array($node->items->type)){
				
				$xml->appendChild(self::make_union($node, true));
			}
			
			if($node->required){
				$skip_min = true;
				$arr_min_attr = self::attr('minOccurs');
				$arr_min_attr->value = 1;
				if($node->minItems) $arr_min_attr->value = $node->minItems;
				
				$xml->appendChild($arr_min_attr);
			}
			
			$arr_attr = self::attr('maxOccurs');
			$arr_attr->value = 'unbounded';
			if($node->maxItems) $arr_attr->value = $node->maxItems;
			
			$xml->appendChild($arr_attr);
			
		}elseif($node->type == 'object'){

			$xml->appendChild(self::make_object($node));
		}elseif($node->enum){
		
			$xml->appendChild(self::make_enum($node));
		}elseif($node->type == 'string'){

			$xml->appendChild(self::make_string($node));
		}elseif($node->type == 'number'){
		
			$xml->appendChild(self::make_number($node));
		}elseif(is_array($node->type)){
			
			$xml->appendChild(self::make_union($node));
		}elseif($node->type == 'boolean'){
			
			$xml->appendChild(self::make_bool($node));
		}else{
		
			$xml->appendChild(new \Domtext($node->type));
		}
		
		$attr_name = $xml->getAttribute('name');
		if($attr_name && $attr_name != 'root' && !$node->required && !$skip_min){
			$min_occur_attr = self::attr('minOccurs');
			$min_occur_attr->value = '0';
			$xml->appendChild($min_occur_attr);
		}
		
		
		return $xml;
	}
	
	private static function make_object($node, $arr_flag = false){
		$ct = self::elem('complexType');
		$seq = self::elem('sequence');
		
		$properties = ($arr_flag) ? $node->items->properties : $node->properties;
		
		foreach($properties as $k => $p){
			
			
			$elem = self::elem('element');
			$attr = self::attr('name');
			$attr->value = $k;
			$elem->appendChild($attr);
			
			$n = self::gen_node($p, $elem);
			
			$seq->appendChild($n);
			
		}
		
		$ct->appendChild($seq);
		return $ct;
	}
	
	private static function make_string($node, $arr_flag = false){
		$restrictions = ($arr_flag) ? $node->items : $node;
		
		if(count(array_intersect(self::$string_restrictions, array_keys((array)$restrictions))) > 0){
			$simple = self::elem('simpleType');
			$res = self::elem('restriction');
			$res_attr = self::attr('base');
			$res_attr->value = self::nsval('string');
			
			
			$res->appendChild($res_attr);
			
			
			if($restrictions->minLength){
				$min = self::elem('minLength');
				$min_attr = self::attr('value');
				$min_attr->value = $restrictions->minLength;
				$min->appendChild($min_attr);
				$res->appendChild($min);
			}
			
			if($restrictions->maxLength){
				$max = self::elem('maxLength');
				$max_attr = self::attr('value');
				$max_attr->value = $restrictions->maxLength;
				$max->appendChild($max_attr);
				$res->appendChild($max);
			}
			
			if($restrictions->pattern){
				$patt = self::elem('pattern');
				
				//haha
				$patt_att = self::attr('value');
				
				//xsd patterns are already anchored at both ends and don't support delimeters or flags
				//so let's get rid of them
				$patt_att->value = preg_replace(array('/^\/?\^?/', '/\$?(\/.*|\/?)$/'), '', $restrictions->pattern);
				//$patt_att->value = substr($node->pattern, 1, -1);
				$patt->appendChild($patt_att);
				
				$res->appendChild($patt);
			}
			
			$simple->appendChild($res);
			
			return $simple;
		}else{
			$str_attr = self::attr('type');
			$str_attr->value = self::nsval('string');
			return $str_attr;
		}
	}
	
	
	private static function make_number($node, $arr_flag = false){
		$restrictions = ($arr_flag) ? $node->items : $node;
		
		if(count(array_intersect(self::$number_restrictions, array_keys((array)$restrictions))) > 0){
			$simple = self::elem('simpleType');
			$res = self::elem('restriction');
			$res_attr = self::attr('base');
			$res_attr->value = self::nsval('integer');
			
			$res->appendChild($res_attr);
			
			if($restrictions->minLength && !$restrictions->minimum){
				$min = self::elem('minInclusive');
				$min_attr = self::attr('value');
				$min_attr->value = str_pad('1', $restrictions->minLength, '0');
				$min->appendChild($min_attr);
				$res->appendChild($min);
			}elseif($restrictions->minimum){
				$min = self::elem('minInclusive');
				$min_attr = self::attr('value');
				$min_attr->value = $restrictions->minimum;
				$min->appendChild($min_attr);
				$res->appendChild($min);
			}
			
			if($restrictions->maxLength && !$restrictions->maximum){
				$max = self::elem('maxInclusive');
				$max_attr = self::attr('value');
				
				//I don't know why, but even though I have tried numerous parsers and
				//looked into the xsd spec for integers, php's domdocument refuses to
				//accept any integer greater than 24 digits. I have tried long, unsignedInteger,
				//decimal, unsignedLong, and nonNegativeInteger, but even though all the docs
				//say that it should be good up to 30 digits and many online validators confirm
				//this, DomDocument don't play by those rules so just truncate this to 24.
				$restrictions->maxLength = ($restrictions->maxLength > 24) ? 24 : $restrictions->maxLength;
				$max_attr->value = str_pad('9', $restrictions->maxLength, '9');
				$max->appendChild($max_attr);
				$res->appendChild($max);
			}elseif($restrictions->maximum){
				$max = self::elem('maxInclusive');
				$max_attr = self::attr('value');
				$max_attr->value = $restrictions->maximum;
				$max->appendChild($max_attr);
				$res->appendChild($max);
			}
			
			$simple->appendChild($res);
			
			return $simple;
		}else{
			$int_attr = self::attr('type');
			$int_attr->value = self::nsval('integer');
			return $int_attr;
		}
	}
	
	private static function make_enum($node, $arr_flag = false){
		$simple = self::elem('simpleType');
		$res = self::elem('restriction');
		$res_attr = self::attr('base');
		$res_attr->value = self::nsval('string');
		
		$res->appendChild($res_attr);
		
		foreach($node->enum as $e){
			$enum = self::elem('enumeration');
			$val = self::attr('value');
			$val->value = $e;
			$enum->appendChild($val);
			$res->appendChild($enum);
		}
		
		$simple->appendChild($res);
		
		return $simple;
	}
	
	private static function make_union($node, $arr_flag = false){
		
		$simple = self::elem('simpleType');
		$union = self::elem('union');
		
		$members = self::attr('memberTypes');
		
		$member_val = '';
		
		$types = ($arr_flag) ? $node->items->type : $node->type;
		
		foreach($types as $t){
			$val = (isset(self::$jtype2xtype[$t])) ? self::$jtype2xtype[$t] : $t;
			$member_val .= self::$ns.$val.' ';
		}
		$members->value = trim($member_val);
		
		$union->appendChild($members);
		
		$simple->appendChild($union);
		
		return $simple;
	}
	
	private static function make_bool($node, $arr_flag = false){
		$bool_attr = self::attr('type');
		$bool_attr->value = self::nsval('boolean');
		return $bool_attr;
	}
	
	private static function elem($elem_name){
		return self::$xml->createElement(self::$ns.$elem_name);
	}
	
	private static function attr($attr_name){
		return self::$xml->createAttribute($attr_name);
	}
	
	private static function nsval($val){
		return self::$ns.$val;
	}
}
