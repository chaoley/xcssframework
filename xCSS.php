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

class xCss
{	
////////////////////////////////////////////////////////////////////////////////
// Attributes
////////////////////////////////////////////////////////////////////////////////
  
	/**
	 * @var $path_css_dir
	 */
	private $pathCssDir;
	
  /**
   * @var $mastercssfile
   */
	private $masterCssFile;
	
  /**
   * @var $xCSSfile
   */
	private $xCSSfile;
	
  /**
   * @var $cssfile
   */
	private $cssFile;
	
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
	private $debugMode;

  /**
   * hole content of the xCSS file
   * @var $filecont
   */
	private $fileContent;
	
  /**
   * an array of keys(selectors) and values(propertys)
   * @var $parts
   */
	private $parts;
	
  /**
   * nodes that will be extended some level later
   * @var $levelparts
   */
	private $levelParts;

  /**
   * final css nodes as an array
   * @var $css
   */
	private $css;

  /**
   * vars declared in xCSS files
   * @var $xCSSvars
   */
	private $xCSSvars;
	
  /**
   * output string for each CSS file
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
		$this->levelParts = array();
		$this->xCSSvars = array();
		
		$this->pathCssDir = isset($cfg['path_to_css_dir']) ? $cfg['path_to_css_dir'] : '../';
		
		if(isset($cfg['xCSS_files']))
		{
			$this->xCSSfiles = array();
			$this->cssFile = array();
			foreach($cfg['xCSS_files'] as $xCSSfile => $cssFile)
			{
				array_push($this->xCSSfiles, $xCSSfile);
				// get rid of the media properties
				$file = explode(':', $cssFile);
				array_push($this->cssFile, trim($file[0]));
			}
		}
		else
		{
			$this->xCSSfiles = array('xCSS.xcss');
			$this->cssFile = array('xCSS_generated.css');
		}
		
		// CSS master file
		if(isset($cfg['master_file']) && $cfg['master_file'] === TRUE)
		{
			$this->masterCssFile = isset($cfg['master_filename']) ? $cfg['master_filename'] : 'master.css';
			
			$reset = isset($cfg['reset_files']) ? $cfg['reset_files'] : null;
			$xcssf = isset($cfg['xCSS_files']) ? $cfg['xCSS_files'] : null;
			$hook = isset($cfg['hook_files']) ? $cfg['hook_files'] : null;
			
			$this->creatMasterFile($reset, $xcssf, $hook);
		}
		
		$this->construct  = isset($cfg['construct_name']) ? $cfg['construct_name'] : 'self';
		$this->compress   = isset($cfg['compress']) ? $cfg['compress'] : false;
		$this->debugMode  = isset($cfg['debugmode']) ? $cfg['debugmode'] : false;
		
		// this is needed to be able to extend selectors across mulitple xCSS files
		$this->xCSSfiles  = array_reverse($this->xCSSfiles);
		$this->cssFile    = array_reverse($this->cssFile);
	}
	
////////////////////////////////////////////////////////////////////////////////
// Public Interface Methodes
////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * 
	 *
	 */
  public function compile()
  {
    
    $numFiles = count($this->xCSSfiles);
    
    for($i=0; $i < $numFiles; $i++)
    {
      $this->parts = null;
      $this->fileContent = null;
      $this->css = null;
      
      $filename = $this->pathCssDir.$this->xCSSfiles[$i];
      if(file_exists($filename))
      {
        $this->fileContent = str_replace('ï»¿', '', utf8_encode(file_get_contents($filename)));
        
        if(strlen($this->fileContent)>1)
        {
          $this->startSplitCont();
          
          if(count($this->parts))
          {
            $this->parseLevel();
            
            $this->manageOrder();
            
            if( ! empty($this->levelParts))
            {
              $this->manageGlobalExtends();
            }
            
            $this->finalParse($this->cssFile[$i]);
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
    
  }//end public function compile */
	
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
		foreach($reset as $file)
		{
			array_push($files, $file);
		}
		foreach($main as $file)
		{
			array_push($files, $file);
		}
		foreach($hook as $file)
		{
			array_push($files, $file);
		}
		
		$masterFileContent = null;
		foreach($files as $file)
		{
			$file = explode(':', $file);
			$props = isset($file[1]) ? ' '.trim($file[1]) : '';
			$masterFileContent .= '@import url("'.trim($file[0]).'")'.$props.';'."\n";
		}
		
		$this->creatFile($masterFileContent, $this->masterCssFile);
		
	}//end private function creatMasterFile */
	

	/**
	 * 
	 * @return void
	 */
	private function startSplitCont()
	{
		// removes multiple line comments
		$this->fileContent = preg_replace("/\/\*(.*)?\*\//Usi", "", $this->fileContent);
		// removes inline comments, but not :// for http://
		$this->fileContent .= "\n";
		$this->fileContent = preg_replace("/[^:]\/\/.+?\n/", "", $this->fileContent);
		
		$this->fileContent = $this->changeBraces($this->fileContent);
		
		$this->fileContent = explode("]}", $this->fileContent);
		
		foreach($this->fileContent as $i => $part)
		{
			$part = trim($part);
			if( ! empty($part))
			{
				list($keyStr, $codeStr) = explode("{[", $part);
				$keyStr = trim($keyStr);
				// adding new line to all (,) in selectors, to be able to find them for 'extends' later
				$keyStr = str_replace(',', ",\n", $keyStr);
				if($keyStr == 'vars')
				{
					$this->setupVars($codeStr);
					unset($this->fileContent[$i]);
				}
				else if($keyStr != '')
				{
					$this->parts[$keyStr] = $codeStr;
				}
			}
		}
		
	}//end private function startSplitCont */
	
	/**
	 * @var string $codeStr
	 *
	 */
	private function setupVars($codeStr)
	{
		$codes = explode(";", $codeStr);
		if(count($codes) )
		{
			foreach($codes as $code)
			{
				$code = trim($code);
				if( ! empty($code))
				{
					list($varKey, $varCode) = explode("=", $code);
					$varKey  = trim($varKey);
					$varCode = trim($varCode);
					if(strlen($varKey))
					{
						$this->xCSSvars[$varKey] = $varCode;
					}
				}
			}
		}
	}//end private function setupVars */
	
	private function useVars($cont)
	{
		foreach($this->xCSSvars as $varKey => $varCode)
		{
			$cont = str_replace($varKey, $varCode, $cont);
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
		
		foreach($this->levelParts as $keyStr => $codeStr)
		{
			if(strpos($keyStr, 'extends') !== FALSE)
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keyStr, $result);
				
				$child  = trim($result[1][0]);
				$parent = trim($result[3][0]);
				
				foreach($this->parts as $pKeyStr => $pCodeStr)
				{
					// to be sure we get all the children we need to find the parent selector
					// this must be the one that has no , after his name
					if(strpos($pKeyStr, ",\n".$child) !== FALSE && ( ! strpos($pKeyStr, $child.",") !== FALSE))
					{
						$pKeys = explode(",\n", $pKeyStr);
						foreach($pKeys as $pKey)
						{
							$this->levelParts[$pKey." extends ".$parent] = '';
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
		foreach($this->parts as $keyStr => $codeStr)
		{
			if(strpos($keyStr, 'extends') !== FALSE)
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keyStr, $result);
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				
				if(strpos($parent, '&') !== FALSE)
				{
					$killThis = $child.' extends '.$parent;
					
					$parents = explode(' & ', $parent);
					$withThisKey = $child.' extends '.$parents[0];
					
					$addKeys = array();
					
					$numParents = count($parents);
					
					for($i = 1; $i < $numParents; $i++)
					{
						array_push($addKeys,$child.' extends '.$parents[$i]);
					}
					
					$this->parts = $this->addNodeAtOrder($killThis, $withThisKey, $codeStr, $addKeys);
				}
			}
		}
	}//end private function manageMultipleExtends */
	
	/**
	 * 
	 *
	 */
	private function addNodeAtOrder($killThis, $withThisKey, $andThisValue, $additionalKey = array())
	{
		foreach($this->parts as $keyStr => $codeStr)
		{
			if($keyStr == $killThis)
			{
				$temp[$withThisKey] = $andThisValue;
				
				if( ! empty($additionalKey))
				{
					foreach($additionalKey as $emptyKey)
					{
						$temp[$emptyKey] = '';
					}
				}
			}
			else
			{
				$temp[$keyStr] = $codeStr;
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
		
		foreach($this->levelParts as $keyStr => $codeStr)
		{
			if(strpos($keyStr, 'extends') !== FALSE)
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keyStr, $result);
				
				$parent = trim($result[3][0]);
				$child  = trim($result[1][0]);
				
				// true means that the parent node was in the same file
				if($this->searchForParent($child, $parent))
				{
					// remove extended rule
					unset($this->levelParts[$keyStr]);
				}
			}
		}

		foreach($this->parts as $keyStr => $codeStr)
		{
			if(strpos($keyStr, 'extends') !== FALSE)
			{
				preg_match_all('/((\S|\s)+?) extends ((\S|\n)[^,]+)/', $keyStr, $result);
				if(count($result[3]) > 1)
				{
					unset($this->parts[$keyStr]);
					$keyStr = str_replace(' extends '.$result[3][0], '', $keyStr);
					$keyStr .= ' extends '.$result[3][0];
					$this->parts[$keyStr] = $codeStr;
					$this->parseExtends();
					break;
				}
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				
				// true means that the parent node was in the same file
				if($this->searchForParent($child, $parent))
				{
					// if not empty, creat own node with extended code
					if( ! preg_match("/^(\s+|)$/", $codeStr))
					{
						$this->parts[$child] = $codeStr;
					}
					
					unset($this->parts[$keyStr]);
				}
				else
				{
					if( ! preg_match("/^(\s+|)$/", $codeStr))
					{
						$this->parts[$child] = $codeStr;
					}
					unset($this->parts[$keyStr]);
					// add this node to levelParts to find it later
					$this->levelParts[$keyStr] = $codeStr;
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
		$parentFound = false;
		foreach ($this->parts as $keyStr => $codeStr)
		{
			$sepKeys = explode(",\n", $keyStr);
			foreach ($sepKeys as $sKey)
			{
				if($parent == $sKey)
				{
					$this->parts = $this->addNodeAtOrder($keyStr, $child.",\n".$keyStr, $codeStr);
					// ever since now the code doesn't make any sens but it works
					// finds all the parent selectors with another bind selectors behind
					foreach ($this->parts as $keyStr => $codeStr)
					{
						$sepKeys = explode(",\n", $keyStr);
						foreach ($sepKeys as $sKey)
						{
							if(strpos($sKey, $parent) !== FALSE && $parent != $sKey)
							{
								$childExtra = str_replace($parent, '', $sKey);
								if(substr($childExtra, 0, 1) == ' ')
								{
									// get rid off not extended parent node
									$this->parts = $this->addNodeAtOrder($keyStr, $child.$childExtra.",\n".$keyStr, $codeStr);
								}
							}
						}
					}
					$parentFound = true;
				}
			}
		}
		return $parentFound;
	}//end private function searchForParent */
	
  /**
   *  
   *
   */
	private function parseChilds()
	{
		$stillChildsLeft = false;
		foreach($this->parts as $keyStr => $codeStr)
		{
			if(strpos($codeStr, '{') !== FALSE)
			{
				$keyStr = trim($keyStr);
				unset($this->parts[$keyStr]);
				unset($this->levelParts[$keyStr]);
				$this->manageChildren($keyStr, $this->construct."{}\n".$codeStr);
				$stillChildsLeft = true; // maybe
			}
		}
		if($stillChildsLeft)
		{
			$this->parseLevel();
		}
	}//end private function parseChilds */
	
  /**
   *  
   *
   */
	private function manageChildren($keyStr, $codeStr)
	{
		$codeStr = $this->changeBraces($codeStr);
		
		$cParts = explode(']}', $codeStr);
		foreach ($cParts as $cPart)
		{
			$cPart = trim($cPart);
			if( ! empty($cPart))
			{
				list($cKeyStr, $cCodeStr) = explode('{[', $cPart);
				$cKeyStr = trim($cKeyStr);

				if($cKeyStr != '')
				{
					$sepKeys = explode(",\n", $keyStr);
					$betterKey = '';

					foreach ($sepKeys as $sKey)
					{
						$betterKey .= $sKey.' '.$cKeyStr.",\n";
					}

					if(strpos($betterKey, $this->construct) !== FALSE)
					{
						$betterKey = str_replace(' '.$this->construct, '', $betterKey);
					}
					$this->parts[substr($betterKey,0,-2)] = $cCodeStr;
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
		foreach ($this->parts as $keyStr => $codeStr)
		{
			// ok let's fide out who has the most 'extends' in his key
			// the more the higher this node will go
			$sepKeys = explode(",\n", $keyStr);
			$order[$keyStr] = count($sepKeys) * -1;
		}
		asort($order);
		foreach ($order as $keyStr => $orderNr)
		{
			// with the sorted order we can now redeclare the values
			$sorted[$keyStr] = $this->parts[$keyStr];
		}
		// and give it back
		$this->parts = $sorted;
	}//end private function manageOrder */

  /**
   *  
   *
   */
	private function finalParse($fileName)
	{
		foreach($this->parts as $keyStr => $codeStr)
		{
			if( ! preg_match("/^(\s+|)$/", $codeStr))
			{
				
			  $codeStr = trim($codeStr);
				if( ! isset($this->css[$keyStr]))
				{
					$this->css[$keyStr] = array();
				}
				$codes = explode(";",$codeStr);
				
				if(count($codes) )
				{
					foreach($codes as $code)
					{
						$code = trim($code);
						if( ! empty($code))
						{
							list($codeKeyStr, $codeValue) = explode(":", $code);
							if(strlen($codeKeyStr) > 0)
							{
								$this->css[$keyStr][trim($codeKeyStr)] = trim($codeValue);
							}
						}
					}
				}
				
			}
		}
		$this->finalFile[$fileName] = $this->creatCSS();
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
	private function creatFile($content, $fileName)
	{
		if($this->debugMode)
		{
			echo "/*\nFILENAME:\n".$fileName."\nCONTENT:\n".$content."*/\n//------------------------------------\n";
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
		
		$filepath = $this->pathCssDir.$fileName;
		$filepath_dirs_arr = explode('/', $filepath);
		$filepath_dirs = null;
		
		$numFilpathDirs = count($filepath_dirs_arr)-1;
		
		for($i = 0; $i < $numFilpathDirs; $i++)
		{
			$filepath_dirs .= $filepath_dirs_arr[$i].'/';
		}
		
		if( ! is_dir($filepath_dirs))
		{
			die("alert(\"No such directory '".$fileName."'\");");
		}
		
		file_put_contents($filepath, pack("CCC",0xef,0xbb,0xbf).utf8_decode($content));
	}//end private function creatFile */
	
}//end class Xcss