<?php 
/**
 * xCSS class
 *
 * @author     Anton Pawlik
 * @author     Dominik Bonsch <dominik.bonsch@webfrap.de>
 * @version    0.9.2
 * @see        http://xcss.antpaw.org/docs/
 * @copyright  (c) 2009 Anton Pawlik
 * @license    http://xcss.antpaw.org/about/
 */

class Xcss
{	
////////////////////////////////////////////////////////////////////////////////
// Attributes
////////////////////////////////////////////////////////////////////////////////
  
	/**
	 * @var $path_css_dir
	 */
	private $path_css_dir;
	
  /**
   * @var $mastercssfile
   */
	private $mastercssfile;
	
  /**
   * @var $xCSSfile
   */
	private $xCSSfile;
	
  /**
   * @var $cssfile
   */
	private $cssfile;
	
  /**
   * @var $construct
   */
	private $construct;
	
  /**
   * @var $compress
   */
	private $compress;
	
  /**
   * @var $debugmode
   */
	private $debugmode;

  /**
   * hole content of the xCSS file
   * @var $filecont
   */
	private $filecont;
	
  /**
   * an array of keys(selectors) and values(propertys)
   * @var $parts
   */
	private $parts;
	
  /**
   * nodes that will be extended some level later
   * @var $levelparts
   */
	private $levelparts;

	// final css nodes as an array
  /**
   * nodes that will be extended some level later
   * @var $css
   */
	private $css;
	
	// vars declared in xCSS files
  /**
   * nodes that will be extended some level later
   * @var $xCSSvars
   */
	private $xCSSvars;
	
	// output string for each CSS file
  /**
   * nodes that will be extended some level later
   * @var $finalFile
   */
	private $finalFile;
	
////////////////////////////////////////////////////////////////////////////////
// Constructor
////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * 
	 * @param array $cfg
	 */
	public function __construct(array $cfg)
	{
		$this->levelparts = array();
		$this->xCSSvars = array();
		
		$this->path_css_dir = isset($cfg['path_to_css_dir']) ? $cfg['path_to_css_dir'] : '../';
		
		if(isset($cfg['xCSS_files']))
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
			$this->xCSSfiles = array('xCSS.xcss');
			$this->cssfile = array('xCSS_generated.css');
		}
		
		// CSS master file
		if(isset($cfg['master_file']) && $cfg['master_file'] === TRUE)
		{
			$this->mastercssfile = isset($cfg['master_filename']) ? $cfg['master_filename'] : 'master.css';
			
			$reset = isset($cfg['reset_files']) ? $cfg['reset_files'] : null;
			$xcssf = isset($cfg['xCSS_files']) ? $cfg['xCSS_files'] : null;
			$hook = isset($cfg['hook_files']) ? $cfg['hook_files'] : null;
			
			$this->creatMasterFile($reset, $xcssf, $hook);
		}
		
		$this->construct = isset($cfg['construct_name']) ? $cfg['construct_name'] : 'self';
		
		$this->compress = isset($cfg['compress']) ? $cfg['compress'] : false;
		
		$this->debugmode = isset($cfg['debugmode']) ? $cfg['debugmode'] : false;
		
		// this is needed to be able to extend selectors across mulitple xCSS files
		$this->xCSSfiles = array_reverse($this->xCSSfiles);
		$this->cssfile = array_reverse($this->cssfile);
	}
	
////////////////////////////////////////////////////////////////////////////////
// Public Interface Methodes
////////////////////////////////////////////////////////////////////////////////
	
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
        $this->filecont = str_replace('ï»¿', '', utf8_encode(file_get_contents($filename)));
        
        if(strlen($this->filecont)>1)
        {
          $this->startSplitCont();
          
          if(count($this->parts) > 0)
          {
            $this->parseLevel();
            
            $this->manageOrder();
            
            if( ! empty($this->levelparts))
            {
              $this->manageGlobalExtends();
            }
            
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
	
////////////////////////////////////////////////////////////////////////////////
// Private internal Methodes
////////////////////////////////////////////////////////////////////////////////
	
  /**
   * @param array $reset
   * @param array $main
   * @param array $hook
   * @return void
   */
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
			$props = isset($file[1]) ? ' '.trim($file[1]) : '';
			$masterFileCont .= '@import url("'.trim($file[0]).'")'.$props.';'."\n";
		}
		
		$this->creatFile($masterFileCont, $this->mastercssfile);
	}//end private function creatMasterFile */
	

	/**
	 * 
	 * @return void
	 */
	private function startSplitCont()
	{
		// removes multiple line comments
		$this->filecont = preg_replace("/\/\*(.*)?\*\//Usi", "", $this->filecont);
		// removes inline comments, but not :// for http://
		$this->filecont .= "\n";
		$this->filecont = preg_replace("/[^:]\/\/.+?\n/", "", $this->filecont);
		
		$this->filecont = $this->changeBraces($this->filecont);
		
		$this->filecont = explode("]}", $this->filecont);
		
		foreach($this->filecont as $i => $part)
		{
			$part = trim($part);
			if( ! empty($part))
			{
				list($keystr, $codestr) = explode("{[", $part);
				$keystr = trim($keystr);
				// adding new line to all (,) in selectors, to be able to find them for 'extends' later
				$keystr = str_replace(',', ",\n", $keystr);
				if($keystr == 'vars')
				{
					$this->setupVars($codestr);
					unset($this->filecont[$i]);
				}
				else if($keystr != '')
				{
					$this->parts[$keystr] = $codestr;
				}
			}
		}
		
	}//end private function startSplitCont */
	
	/**
	 * 
	 *
	 */
	private function setupVars($codestr)
	{
		$codes = explode(";", $codestr);
		if(count($codes) > 0)
		{
			foreach($codes as $code)
			{
				$code = trim($code);
				if( ! empty($code))
				{
					list($varkey, $varcode) = explode("=", $code);
					$varkey = trim($varkey);
					$varcode = trim($varcode);
					if(strlen($varkey) > 0)
					{
						$this->xCSSvars[$varkey] = $varcode;
					}
				}
			}
		}
	}//end private function setupVars */
	
	private function useVars($cont)
	{
		foreach($this->xCSSvars as $varkey => $varcode)
		{
			$cont = str_replace($varkey, $varcode, $cont);
		}
		return $cont;
	}//end private function useVars */

	private function parseLevel()
	{
		// this will manage xCSS rule: 'extends'
		$this->parseExtends();

		// this will manage xCSS rule: child objects inside of a node
		$this->parseChilds();
	}//end private function parseLevel */
	
	
	private function manageGlobalExtends()
	{
		// helps to find all the extenders of the global extended selector
		
		foreach($this->levelparts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keystr, $result);
				
				$child = trim($result[1][0]);
				$parent = trim($result[3][0]);
				
				foreach($this->parts as $p_keystr => $p_codestr)
				{
					// to be sure we get all the children we need to find the parent selector
					// this must be the one that has no , after his name
					if(strpos($p_keystr, ",\n".$child) !== FALSE && ( ! strpos($p_keystr, $child.",") !== FALSE))
					{
						$p_keys = explode(",\n", $p_keystr);
						foreach($p_keys as $p_key)
						{
							$this->levelparts[$p_key." extends ".$parent] = '';
						}
					}
				}
			}
		}
	}//end private function manageGlobalExtends */
	
	/**
	 *  
	 *
	 */
	private function manageMultipleExtends()
	{
		//	To be able to manage multiple extends, you need to
		//	destroy the actual node and creat many nodes that have
		//	mono extend. the first one gets all the css rules
		foreach($this->parts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keystr, $result);
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				
				if(strpos($parent, '&') !== FALSE)
				{
					$kill_this = $child.' extends '.$parent;
					
					$parents = explode(' & ', $parent);
					$with_this_key = $child.' extends '.$parents[0];
					
					$add_keys = array();
					for($i = 1; $i < count($parents); $i++)
					{
						array_push($add_keys,$child.' extends '.$parents[$i]);
					}
					
					$this->parts = $this->addNodeAtOrder($kill_this, $with_this_key, $codestr, $add_keys);
				}
			}
		}
	}//end private function manageMultipleExtends */
	
	/**
	 * 
	 *
	 */
	private function addNodeAtOrder($kill_this, $with_this_key, $and_this_value, $additional_key = array())
	{
		foreach($this->parts as $keystr => $codestr)
		{
			if($keystr == $kill_this)
			{
				$temp[$with_this_key] = $and_this_value;
				
				if( ! empty($additional_key))
				{
					foreach($additional_key as $empty_key)
					{
						$temp[$empty_key] = '';
					}
				}
			}
			else
			{
				$temp[$keystr] = $codestr;
			}
		}
		
		return $temp;
	}//end private function addNodeAtOrder */
	
	/**
	 *  
	 *
	 */
	private function parseExtends()
	{
		// this will manage xCSS rule: 'extends &'
		$this->manageMultipleExtends();
		
		foreach($this->levelparts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keystr, $result);
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				
				// true means that the parent node was in the same file
				if($this->searchForParent($child, $parent))
				{
					// remove extended rule
					unset($this->levelparts[$keystr]);
				}
			}
		}

		foreach($this->parts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
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
					// add this node to levelparts to find it later
					$this->levelparts[$keystr] = $codestr;
				}
			}
		}
	}//end private function parseExtends */
	
	/**
	 *  
	 *
	 */
	private function searchForParent($child, $parent)
	{
		$parent_found = false;
		foreach ($this->parts as $keystr => $codestr)
		{
			$sep_keys = explode(",\n", $keystr);
			foreach ($sep_keys as $s_key)
			{
				if($parent == $s_key)
				{
					$this->parts = $this->addNodeAtOrder($keystr, $child.",\n".$keystr, $codestr);
					// ever since now the code doesn't make any sens but it works
					// finds all the parent selectors with another bind selectors behind
					foreach ($this->parts as $keystr => $codestr)
					{
						$sep_keys = explode(",\n", $keystr);
						foreach ($sep_keys as $s_key)
						{
							if(strpos($s_key, $parent) !== FALSE && $parent != $s_key)
							{
								$childextra = str_replace($parent, '', $s_key);
								if(substr($childextra, 0, 1) == ' ')
								{
									// get rid off not extended parent node
									$this->parts = $this->addNodeAtOrder($keystr, $child.$childextra.",\n".$keystr, $codestr);
								}
							}
						}
					}
					$parent_found = true;
				}
			}
		}
		return $parent_found;
	}//end private function searchForParent */
	
  /**
   *  
   *
   */
	private function parseChilds()
	{
		$still_childs_left = false;
		foreach($this->parts as $keystr => $codestr)
		{
			if(strpos($codestr, '{') !== FALSE)
			{
				$keystr = trim($keystr);
				unset($this->parts[$keystr]);
				unset($this->levelparts[$keystr]);
				$this->manageChildren($keystr, $this->construct."{}\n".$codestr);
				$still_childs_left = true; // maybe
			}
		}
		if($still_childs_left)
		{
			$this->parseLevel();
		}
	}//end private function parseChilds */
	
  /**
   *  
   *
   */
	private function manageChildren($keystr, $codestr)
	{
		$codestr = $this->changeBraces($codestr);
		
		$c_parts = explode(']}', $codestr);
		foreach ($c_parts as $c_part)
		{
			$c_part = trim($c_part);
			if( ! empty($c_part))
			{
				list($c_keystr, $c_codestr) = explode('{[', $c_part);
				$c_keystr = trim($c_keystr);

				if($c_keystr != '')
				{
					$sep_keys = explode(",\n", $keystr);
					$betterKey = '';

					foreach ($sep_keys as $s_key)
					{
						$betterKey .= $s_key.' '.$c_keystr.",\n";
					}

					if(strpos($betterKey, $this->construct) !== FALSE)
					{
						$betterKey = str_replace(' '.$this->construct, '', $betterKey);
					}
					$this->parts[substr($betterKey,0,-2)] = $c_codestr;
				}
			}
		}
	}//end private function manageChildren */
	
  /**
   *  
   *
   */
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
	}//end private function changeBraces */
	
  /**
   *  
   *
   */
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
	}//end private function manageOrder */

  /**
   *  
   *
   */
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
						if( ! empty($code))
						{
							list($codekeystr, $codevalue) = explode(":", $code);
							if(strlen($codekeystr) > 0)
							{
								$this->css[$keystr][trim($codekeystr)] = trim($codevalue);
							}
						}
					}
				}
			}
		}
		$this->finalFile[$filename] = $this->creatCSS();
	}//end private function finalParse */
	
  /**
   *  
   *
   */
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
	}//end private function creatCSS */
	
  /**
   *  
   *
   */
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
		
		$filepath = $this->path_css_dir.$filename;
		$filepath_dirs_arr = explode('/', $filepath);
		$filepath_dirs = null;
		
		for($i = 0; $i < (count($filepath_dirs_arr)-1); $i++)
		{
			$filepath_dirs .= $filepath_dirs_arr[$i].'/';
		}
		
		if( ! is_dir($filepath_dirs))
		{
			die("alert(\"No such directory '".$filename."'\");");
		}
		
		file_put_contents($filepath, pack("CCC",0xef,0xbb,0xbf).utf8_decode($content));
	}//end private function creatFile */
	
}//end class Xcss