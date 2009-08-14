<?php defined('XCSSCLASS') OR die('No direct access allowed.');
/**
 * xCSS class
 *
 * @author		Anton Pawlik
 * @version		0.1.2
 * @see			http://xcss.antpaw.de/docs/
 * @copyright	(c) 2009 Anton Pawlik
 * @license		http://xcss.antpaw.de/about/
 */

class xCSS
{
	private
	// config vars
	$path_css_dir,
	$mastercssfile,
	$xCSSfile,
	$cssfile,
	$construct,
	$compress,
	$debugmode,
	
	// hole content of the xCSS file
	$filecont,
	
	// an array of keys(selectors) and values(propertys)
	$parts,
	
	// final css nodes as an array
	$css,
	
	// nodes that will be extended across xCSS files
	$globalparts,
	
	// vars declared in xCSS files
	$xCSSvars,
	
	// output string for each CSS file
	$finalFile;
	
	public function __construct(array $cfg)
	{
		$this->globalparts = array();
		$this->xCSSvars = array();
		
		$this->path_css_dir = (@$cfg['path_to_css_dir']) ? $cfg['path_to_css_dir'] : '../';
		
		if(@$cfg['xCSS_files'])
		{
			$this->xCSSfiles = array();
			$this->cssfile = array();
			foreach($cfg['xCSS_files'] as $xCSSfile => $cssfile)
			{
				array_push($this->xCSSfiles, $xCSSfile);
				// get rid of the media properties
				$file = explode(':', $cssfile);
				array_push($this->cssfile, trim($file[0]));
			}
		}
		else
		{
			$this->xCSSfiles = array('xCSS.xCSS');
			$this->cssfile = array('xCSS_generated.css');
		}
		
		// CSS master file
		if(@$cfg['master_file'])
		{
			$this->mastercssfile = (@$cfg['master_filename']) ? $cfg['master_filename'] : 'master.css';
			$this->creatMasterFile(@$cfg['reset_files'], @$cfg['xCSS_files'], @$cfg['hook_files']);
		}
		
		$this->construct = (@$cfg['construct_name']) ? $cfg['construct_name'] : 'self';
		
		$this->compress = (bool) @$cfg['compress'];
		
		$this->debugmode = (bool) @$cfg['debugmode'];
		
		// this is needed to be able to extend selectors across mulitple xCSS files
		$this->xCSSfiles = array_reverse($this->xCSSfiles);
		$this->cssfile = array_reverse($this->cssfile);
	}
	
	private function creatMasterFile(array $reset = array(), array $main = array(), array $hook = array())
	{
		$files = array();
		foreach($reset as $fiel)
		{
			array_push($files, $fiel);
		}
		foreach($main as $fiel)
		{
			array_push($files, $fiel);
		}
		foreach($hook as $fiel)
		{
			array_push($files, $fiel);
		}
		
		$masterFileCont = null;
		foreach($files as $file)
		{
			$file = explode(':', $file);
			$props = (@$file[1]) ? ' '.trim($file[1]) : '';
			$masterFileCont .= '@import url("'.trim($file[0]).'")'.$props.';'."\n";
		}
		
		$this->creatFile($masterFileCont, $this->mastercssfile);
	}
	
	public function compile()
	{
		for($i=0; $i < count($this->xCSSfiles); $i++)
		{
			$this->parts = null;
			$this->filecont = null;
			$this->css = null;
			
			$filename = $this->path_css_dir.$this->xCSSfiles[$i];
			if(file_exists($filename))
			{
				$this->filecont = file_get_contents($filename);
				
				if(strlen($this->filecont)>1)
				{
					$this->startSplitCont();

					if(count($this->parts) > 0)
					{
						$this->parseLevel();
					
						$this->manageOrder();
						
						$this->finalParse($this->cssfile[$i]);
					}
				}
			}
			else
			{
				die("alert(\"Can't find '".$filename."'\");");
			}
		}
		
		if( ! empty($this->finalFile))
		{
			foreach($this->finalFile as $fname => $fcont)
			{
				$this->creatFile($this->useVars($fcont), $fname);
			}
		}
	}
	
	private function startSplitCont()
	{
		// removes multiple line comments
		$this->filecont = preg_replace("/\/\*(.*)?\*\//Usi", "", $this->filecont);
		// removes inline comments, but not :// for http://
		$this->filecont = preg_replace("/[^:]\/\/.+?\n/", "", $this->filecont);
		
		$this->filecont = $this->changeBraces($this->filecont);
		
		$this->filecont = explode("]}",$this->filecont);

		foreach($this->filecont as $i => $part)
		{
			@list($keystr, $codestr) = explode("{[", $part);
			$keystr = trim($keystr);
			// adding new line to all (,) in selectors, to be able to find them for 'extends' later
			$keystr = preg_replace("/(,)((\w|\s)+)?\b/", ",\n$2", $keystr);
			if($keystr == 'vars')
			{
				$this->setupVars($codestr);
				unset($this->filecont[$i]);
			}
			elseif($keystr != '')
			{
				$this->parts[$keystr] = $codestr;
			}
		}
	}
	
		private function setupVars($codestr)
		{
			$codes = explode(";", $codestr);
			if(count($codes) > 0)
			{
				foreach($codes as $code)
				{
					$code = trim($code);
					@list($varkey, $varcode) = explode("=", $code);
					$varkey = trim($varkey);
					$varcode = trim($varcode);
					if(strlen($varkey) > 0)
					{
						$this->xCSSvars[$varkey] = $varcode;
					}
				}
			}
		}
		
		private function useVars($cont)
		{
			foreach($this->xCSSvars as $varkey => $varcode)
			{
				$cont = str_replace($varkey, $varcode, $cont);
			}
			return $cont;
		}
	
	private function parseLevel()
	{
		// this will manage xCSS rule: 'extends'
		$this->parseExtends();
		
		// this will manage xCSS rule: child objects inside of a node
		$this->parseChilds();
	}
	
	private function parseExtends()
	{
		foreach($this->globalparts as $keystr => $codestr)
		{
			if(eregi("extends", $keystr))
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keystr, $result);
				
				$parent = trim($result[3][0]); 	// parent class
				$child = trim($result[1][0]); 	// child class
				
				// true means that the parent node was in the same file
				if($this->searchForParent($child, $parent))
				{
					// remove extended rule
					unset($this->globalparts[$keystr]);
				}
			}
		}
		
		foreach($this->parts as $keystr => $codestr)
		{
			if(eregi("extends", $keystr))
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keystr, $result);
				if(count($result[3]) > 1)
				{
					unset($this->parts[$keystr]);
					$keystr = str_replace(' extends '.$result[3][0], '', $keystr);
					$keystr .= ' extends '.$result[3][0];
					$this->parts[$keystr] = $codestr;
					$this->parseExtends();
					break;
				}
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				
				if(eregi("&", $parent))
				{
					$this->manageMultipleExtends($child, $parent, $codestr);
					$this->parseExtends();
					break;
				}
				
				// true means that the parent node was in the same file
				if($this->searchForParent($child, $parent))
				{
					// if not empty, creat own node with extended code
					if( ! preg_match("/^(\s+|)$/", $codestr))
					{
						$this->parts[$child] = $codestr;
					}
					
					unset($this->parts[$keystr]);
				}
				else
				{
					if( ! preg_match("/^(\s+|)$/", $codestr))
					{
						$this->parts[$child] = $codestr;
					}
					unset($this->parts[$keystr]);
					// add this node to globalparts to find it later
					$this->globalparts[$keystr] = $codestr;
				}
			}
		}
	}
	
		private function searchForParent($child, $parent)
		{
			foreach ($this->parts as $keystr => $codestr)
			{
				$sep_keys = explode(",\n", $keystr);
				foreach ($sep_keys as $s_key)
				{
					if($parent == $s_key)
					{
						$this->parts[$child.",\n".$keystr] = $codestr;
				
						// get rid off not extended parent node
						unset($this->parts[$keystr]);
						// ever since now the code doesn't make any sens but it works
						// finds all the parent selectors with another bind selectors behind
						foreach ($this->parts as $keystr => $codestr)
						{
							$sep_keys = explode(",\n", $keystr);
							foreach ($sep_keys as $s_key)
							{
								if(eregi($parent, $s_key) && $parent != $s_key)
								{
									$childextra = str_replace($parent, '', $s_key);
									if(substr($childextra, 0, 1) == ' ')
									{
										$this->parts[$child.$childextra.",\n".$keystr] = $codestr;
										// get rid off not extended parent node
										unset($this->parts[$keystr]);
									}
								}
							}
						}
						return true;
					}
				}
			}
			return false;
		}
		
		private function manageMultipleExtends($child, $parents, $codestr)
		{
			/*
				To be able to manage multiple extends, you need to
				destroy the actuall node and creat many nodes that have
				mono extend. the first one gets all the css rules
			*/
			unset($this->parts[$child.' extends '.$parents]);
			$parents = explode(' & ', $parents);
			$this->parts[$child.' extends '.$parents[0]] = $codestr;

			for($i = 1; $i < count($parents); $i++)
			{
				$this->parts[$child.' extends '.$parents[$i]] = '';
			}
		}
		
	private function parseChilds()
	{
		$still_childs_left = false;
		foreach($this->parts as $keystr => $codestr)
		{
			if(ereg("\{", $codestr))
			{
				$keystr = trim($keystr);
				unset($this->parts[$keystr]);
				unset($this->globalparts[$keystr]);
				$this->manageChildren($keystr, $this->construct."{}\n".$codestr);
				$still_childs_left = true; // maybe
			}
		}
		if($still_childs_left)
		{
			$this->parseLevel();
		}
	}
	
		private function manageChildren($keystr, $codestr)
		{
			$codestr = $this->changeBraces($codestr);
			
			$c_parts = explode(']}', $codestr);
			foreach ($c_parts as $c_part)
			{
				@list($c_keystr, $c_codestr) = explode('{[', $c_part);
				$c_keystr = trim($c_keystr);
				if($c_keystr != '')
				{
					$sep_keys = explode(",\n", $keystr);
					$betterKey = '';
					foreach ($sep_keys as $s_key)
					{
						$betterKey .= $s_key.' '.$c_keystr.",\n";
					}
					if(eregi($this->construct, $betterKey))
					{
						$betterKey = str_replace(' '.$this->construct, '', $betterKey);
					}
					$this->parts[substr($betterKey,0,-2)] = $c_codestr;
				}
			}
		}
		
		private function changeBraces($str)
		{
			/*
				This function was writen by Gumbo
				http://www.tutorials.de/forum/members/gumbo.html
				Thank you very much!
				
				finds the very outer braces and changes them to {[ code ]}
			*/
			$buffer = '';
			$depth = 0;
			for ($i=0; $i < strlen($str); $i++)
			{
				$char = $str[$i];
				switch ($char)
				{
					case '{':
						$depth++;
						if ($depth === 1)
						{
							$buffer .= '{[';
						}
						else
						{
							$buffer .= $char;
						}
						break;
					case '}':
						$depth--;
						if ($depth === 0)
						{
							$buffer .= ']}';
							continue;
						}
						else
						{
							$buffer .= $char;
						}
						break;
					default:
						$buffer .= $char;
				}
			}
			return $buffer;
		}
	
	private function manageOrder()
	{
		/*
			this function brings the CSS nodes in the right order
			becouse the last value always wins
		*/
		foreach ($this->parts as $keystr => $codestr)
		{
			// ok let's fide out who has the most 'extends' in his key
			// the more the higher this node will go
			$sep_keys = explode(",\n", $keystr);
			$order[$keystr] = count($sep_keys) * -1;
		}
		asort($order);
		foreach ($order as $keystr => $orderNr)
		{
			// with the sorted order we can now redeclare the values
			$sorted[$keystr] = $this->parts[$keystr];
		}
		// and give it back
		$this->parts = $sorted;
	}

	private function finalParse($filename)
	{
		foreach($this->parts as $keystr => $codestr)
		{
			if( ! preg_match("/^(\s+|)$/", $codestr))
			{
				$codestr = trim($codestr);
				if( ! isset($this->css[$keystr]))
				{
					$this->css[$keystr] = array();
				}
				$codes = explode(";",$codestr);
				if(count($codes) > 0)
				{
					foreach($codes as $code)
					{
						$code = trim($code);
						@list($codekeystr, $codevalue) = explode(":", $code);
						if(strlen($codekeystr) > 0)
						{
							$this->css[$keystr][trim($codekeystr)] = trim($codevalue);
						}
					}
				}
			}
		}
		$this->finalFile[$filename] = $this->creatCSS();
	}
	
	private function creatCSS()
	{
		$result = null;
		if(is_array($this->css))
		{
			foreach($this->css as $key => $values)
			{
				// feel free to modifie the indentations the way you like it
				$result .= $key." {\n";
				foreach($values as $key => $value)
				{
					$result .= "	$key: $value;\n";
				}
				$result .= "}\n";
			}
			$result = preg_replace('/\n+/', "\n", $result);
		}
		
		return $result;
	}
	
	private function creatFile($content, $filename)
	{
		if($this->debugmode)
		{
			echo "/*\nFILENAME:\n".$filename."\nCONTENT:\n".$content."*/\n//------------------------------------\n";
		}
		else
		{
			header('Content-type: application/javascript; charset=utf-8');
		}
		
		if($this->compress)
		{
			// let's remove big spaces, tabs and newlines
			$content = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '   ', '    '), '', $content);
		}
		
		if( ! @file_put_contents($this->path_css_dir.$filename, pack("CCC",0xef,0xbb,0xbf).$content))
		{
			die("alert(\"No such directory '".$filename."'\");");
		}
	}
}