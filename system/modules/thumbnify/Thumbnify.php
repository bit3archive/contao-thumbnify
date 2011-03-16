<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  InfinitySoft 2010
 * @author     Tristan Lins <tristan.lins@infinitysoft.de>
 * @package    Thumbnify
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 * Class Thumbnify
 *
 * Thumbnail generation class.
 * @copyright  InfinitySoft 2010
 * @author     Tristan Lins <tristan.lins@infinitysoft.de>
 * @package    Thumbnify
 */
class Thumbnify extends Controller
{
	/**
	 * Default watermark location.
	 */
	private $strDefaultWatermarkLocation = 'SouthEast';
	
	
	/**
	 * Mime type related watermarks.
	 * 
	 * @var array
	 */
	private $arrWatermarks = array();

	
	public function __get($k)
	{
		switch ($k)
		{
		case 'defaultWatermarkLocation':
			return $this->strDefaultWatermarkLocation;
		}
		return parent::__get($k);
	}
	
	
	public function __set($k, $v)
	{
		switch ($k)
		{
		case 'defaultWatermarkLocation':
			switch ($v)
			{
			case 'NorthWest':
			case 'North':
			case 'NorthEast':
			case 'West':
			case 'Center':
			case 'East':
			case 'SouthWest':
			case 'South':
			case 'SouthEast':
				$this->strDefaultWatermarkLocation = $v;
				break;
			}
			break;
			
		default:
			parent::__set($k, $v);
		}
	}
	

	/**
	 * Add a watermark file.
	 * 
	 * @param string
	 * @param string
	 * @param string
	 */
	public function setMimeWatermark($strMime, $strWatermark, $strLocation = null)
	{
		switch ($strLocation)
		{
		case 'NorthWest':
		case 'North':
		case 'NorthEast':
		case 'West':
		case 'Center':
		case 'East':
		case 'SouthWest':
		case 'South':
		case 'SouthEast':
			break;
		default:
			$strLocation = '';
		}
		$this->arrWatermarks[$strMime] = array
		(
			'file' => $strWatermark,
			'location' => $strLocation
		);
	}
	
	
	/**
	 * Test if thumbnail generation is supported for the file.
	 * 
	 * @param string $strFile
	 * @return bool
	 */
	public function isThumbSupported($strFile)
	{
		if (file_exists(TL_ROOT . '/' . $strFile))
		{
			$objFile = new File($strFile);
			return $this->isThumbSupportedForMime($objFile->mime);
		}
		return false;
	}

	
	/**
	 * Test if thumbnail generation is supported for the file.
	 * 
	 * @param string $strMime
	 * @return bool
	 */
	public function isThumbSupportedForMime($strMime)
	{
		return (preg_match('#^(image|video)/.*|application/pdf$#', $strMime)) ? true : false;
	}

	/**
	 * Generate a thumbnail
	 *
	 * @param string
	 * @param integer
	 * @param integer
	 * @param string
	 * @return string
	 */
	public function getThumb($strFile, $intWidth, $intHeight, $strMode = 'box', $strTarget = null)
	{
		if (file_exists(TL_ROOT . '/' . $strFile))
		{
			$objFile = new File($strFile);

			// from image
			if ($objFile->isGdImage)
			{
				return $this->getImage($objFile->value, $intWidth, $intHeight, $strMode, $strTarget);
			}
			
			// generate by mime type
			else
			{
				$strMime = $objFile->mime;
				
				if ($strMime == 'application/octet-stream' && function_exists('finfo_file'))
				{
					$f = finfo_open();
					$strMime = finfo_file($f, TL_ROOT . '/' . $objFile->value, FILEINFO_MIME);
					finfo_close($f);
				}
				
				// from pdf
				if ($strMime == 'application/pdf')
				{
					if (!$strTarget)
					{
						$strTarget = sprintf('system/html/thumb-pdf-%s-%s.jpg', $objFile->filename, substr(md5($intWidth . '-' . $intHeight . '-' . $objFile->value . '-' . $objFile->mtime), 0, 8));
					}
					
					// generate if file does not exists or file is outdated
					if (!file_exists(TL_ROOT . '/' . $strTarget) || $objFile->mtime > filemtime(TL_ROOT . '/' . $strTarget))
					{
						if ($this->executeProc(
							'convert',
							TL_ROOT . '/' . $objFile->value . '[0]',
							'-scale',
							$intWidth . 'x' . $intHeight,
							TL_ROOT . '/' . $strTarget))
						{
							return $strTarget;
						}
					}
					
					// file exists and is up to date
					else
					{
						return $strTarget;
					}
				}
				
				// from other image formats
				elseif (preg_match('#^image/#', $strMime))
				{
					if (!$strTarget)
					{
						$strTarget = sprintf('system/html/image-%s-%s.png', $objFile->filename, substr(md5($intWidth . '-' . $intHeight . '-' . $objFile->value . '-' . $objFile->mtime), 0, 8));
					}
					
					// generate if file does not exists or file is outdated
					if (!file_exists(TL_ROOT . '/' . $strTarget) || $objFile->mtime > filemtime(TL_ROOT . '/' . $strTarget))
					{
						if ($this->executeProc(
							'convert',
							TL_ROOT . '/' . $objFile->value,
							'-resize', '3000x3000>',
							TL_ROOT . '/' . $strTarget))
						{
							return $this->getImage($strTarget, $intWidth, $intHeight, $strMode, $strTarget);
						}
					}
					
					// file exists and is up to date
					else
					{
						return $strTarget;
					}
				}
				
				// from a video file
				elseif (preg_match('#^video/#', $strMime))
				{
					if (!$strTarget)
					{
						$strTarget = sprintf('system/html/thumb-video-%s-%s.jpg', $objFile->filename, substr(md5($intWidth . '-' . $intHeight . '-' . $objFile->value . '-' . $objFile->mtime), 0, 8));
					}
					
					// generate if file does not exists or file is outdated
					if (!file_exists(TL_ROOT . '/' . $strTarget) || $objFile->mtime > filemtime(TL_ROOT . '/' . $strTarget))
					{
						// find a temporary directory for mplayer output
						do {
							$strTemp = 'system/html/mplayer-' . md5(time() . rand());
						} while (is_dir(TL_ROOT . '/' . $strTemp));
						mkdir(TL_ROOT . '/' . $strTemp);
						
						// execute mplayer
						if ($this->executeProc(
							'mplayer',
							'-ss', '1',
							'-nosound',
							'-frames', '1',
							'-vo', 'jpeg:outdir=' . TL_ROOT . '/' . $strTemp,
							TL_ROOT . '/' . $objFile->value
							))
						{
							// generate thumb
							$strTarget = $this->getImage($strTemp . '/00000001.jpg', $intWidth, $intHeight, $strMode, $strTarget);
							
							// delete temporary files
							unlink(TL_ROOT . '/' . $strTemp . '/00000001.jpg');
							rmdir(TL_ROOT . '/' . $strTemp);
							
							return $strTarget;
						}
						
						// execution fails, delete temporary directory
						else
						{
							rmdir($strTemp);
						}
					}
					
					// file exists and is up to date
					else
					{
						return $strTarget;
					}
				}
				
				// other sources
				else
				{
					if (isset($GLOBALS['TL_HOOKS']['thumbnify']) && is_array($GLOBALS['TL_HOOKS']['thumbnify']))
					{
						foreach ($GLOBALS['TL_HOOKS']['thumbnify'] as $callback)
						{
							$this->import($callback[0]);
							$strResult = $this->$callback[0]->$callback[1]($objFile, $intWidth, $intHeight, $strTarget);
							if ($strResult)
							{
								return $strResult;
							}
						}
					}
				}
			}
		}
		return false;
	}


	/**
	 * Generate a watermarked thumbnail
	 *
	 * @param string
	 * @param integer
	 * @param integer
	 * @param string
	 * @return string
	 */
	public function getWatermarkedThumb($strFile, $intWidth, $intHeight, $strMode = 'box', $strTarget = null)
	{
		if (file_exists(TL_ROOT . '/' . $strFile))
		{
			// generate a thumb
			$strThumb = $this->getThumb($strFile, $intWidth, $intHeight, $strMode, $strTarget);

			if ($strThumb)
			{
				// the original file
				$objFile = new File($strFile);
				$strMime = $objFile->mime;
				return $this->addWatermark($strThumb, $strMime);
			}

			return $strThumb;
		}
		return false;
	}
	
	
	/**
	 * Add a watermarked version of the file. The watermark is related to the given mime type or the file mime type.
	 * 
	 * @param unknown_type $strFile
	 * @param unknown_type $strMime
	 * @param unknown_type $strTarget
	 */
	public function addWatermark($strFile, $strMime = null, $strTarget = null)
	{
		if (file_exists(TL_ROOT . '/' . $strFile))
		{
			$objFile = new File($strFile);
			if (is_null($strMime))
			{
				$strMime = $objFile->mime;
			}
			$strMimeGroup = substr($strMime, 0, strpos($strMime, '/'));
			
			// the watermark setup
			$arrWatermark = null;
							
			// exact mime type match
			if ($this->arrWatermarks[$strMime])
			{
				$arrWatermark = $this->arrWatermarks[$strMime];
			}
			
			// mime type group match
			else if ($this->arrWatermarks[$strMimeGroup])
			{
				$arrWatermark = $this->arrWatermarks[$strMimeGroup];
			}
			
			// watermark for all types
			else if ($this->arrWatermarks['all'])
			{
				$arrWatermark = $this->arrWatermarks['all'];
			}
			
			// generate watermarked file
			if (!is_null($arrWatermark) && file_exists(TL_ROOT . '/' . $arrWatermark['file']))
			{
				if (!$arrWatermark['location'])
				{
					$arrWatermark['location'] = $this->strDefaultWatermarkLocation;
				}
				if (!$strTarget)
				{
					$strTarget = 'system/html/' . $objFile->filename . '-watermarked-' . substr(md5(implode(',',$arrWatermark)), 0, 8) . '.' . $objFile->extension;
				}
				
				// generate if file does not exists or file is outdated
				if (!file_exists(TL_ROOT . '/' . $strTarget) || $objFile->mtime > filemtime(TL_ROOT . '/' . $strTarget))
				{
					// composite the files
					if ($this->executeProc(
						'composite',
						'-gravity', $arrWatermark['location'],
						TL_ROOT . '/' . $arrWatermark['file'],
						'-type', 'TrueColorMatte',
						TL_ROOT . '/' . $strFile,
						TL_ROOT . '/' . $strTarget))
					{
						return $strTarget;
					}
					// else: just ignore, error message is generated to system log
				}
				
				// file exists and is up to date
				else
				{
					return $strTarget;
				}
			}
			
			return $strFile;
		}
		return false;
	}


	/**
	 * Execute external program.
	 *
	 * @arg mixed...
	 * @return boolean
	 */
	protected function executeProc()
	{
		// proc args
		$arrArgs = func_get_args();
		
		// add the command
		$strCmd = escapeshellcmd(array_shift($arrArgs));
		
		// add the command parameters
		foreach ($arrArgs as $strArg)
		{
			$strCmd .= ' ' . escapeshellarg($strArg);
		}
		
		// execute the command
		$proc = proc_open(
			$strCmd,
			array(
				0 => array("pipe", "r"),
				1 => array("pipe", "w"),
				2 => array("pipe", "w")
			),
			$arrPipes);
		
		// test if command start failed
		if ($proc === false)
		{
			$this->log('could not execute command: "' . $strCmd . '"', 'Thumbnify::executeProc', TL_ERROR);
			return false;
		}
		
		// close stdin
		fclose($arrPipes[0]);
		
		// read and close stdout
		$strOut = stream_get_contents($arrPipes[1]);
		fclose($arrPipes[1]);
		
		// read and close stderr
		$strErr = stream_get_contents($arrPipes[2]);
		fclose($arrPipes[2]);
		
		// wait until process terminates
		$intCode = proc_close($proc);
		
		// log if process does not terminate without errors
		if ($intCode != 0)
		{
			$this->log('program execution failed<br/>' . "\n" . 'command: "' . $strCmd . '"<br/>' . "\n" . 'stdout: "' . $strOut . '"<br/>' . "\n" . 'stderr: "' . $strErr . '"', 'Thumbnify::executeProc', TL_ERROR);
			return false;
		}
		
		return true;
	}
}
