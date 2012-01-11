<?php
// matmascmd (Matmas Commander)
// Copyright (c) 2005 by Martin Riesz
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

session_start();
@set_time_limit(0);
@dl("php_zlib.dll");
define("PCLZIPLIB", "pclzip.lib.php");


/*
		=======================================================================
Name:
		tar Class

Author:
		Josh Barger <joshb@npt.com>

Description:
		This class reads and writes Tape-Archive (TAR) Files and Gzip
		compressed TAR files, which are mainly used on UNIX systems.
		This class works on both windows AND unix systems, and does
		NOT rely on external applications!! Woohoo!

Usage:
		Copyright (C) 2002  Josh Barger

		This library is free software; you can redistribute it and/or
		modify it under the terms of the GNU Lesser General Public
		License as published by the Free Software Foundation; either
		version 2.1 of the License, or (at your option) any later version.

		This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	Lesser General Public License for more details at:
		http://www.gnu.org/copyleft/lesser.html

		If you use this script in your application/website, please
		send me an e-mail letting me know about it :)

Bugs:
		Please report any bugs you might find to my e-mail address
		at joshb@npt.com.  If you have already created a fix/patch
		for the bug, please do send it to me so I can incorporate it into my release.

Version History:
		1.0	04/10/2002	- InitialRelease

		2.0	04/11/2002	- Merged both tarReader and tarWriter
		classes into one
		- Added support for gzipped tar files
		Remember to name for .tar.gz or .tgz
		if you use gzip compression!
	:: THIS REQUIRES ZLIB EXTENSION ::
		- Added additional comments to
		functions to help users
		- Added ability to remove files and
		directories from archive
		2.1	04/12/2002	- Fixed serious bug in generating tar
		- Created another example file
		- Added check to make sure ZLIB is
		installed before running GZIP
		compression on TAR
		2.2	05/07/2002	- Added automatic detection of Gzipped
		tar files (Thanks go to Jürgen Falch
		for the idea)
		- Changed "private" functions to have
		special function names beginning with
		two underscores
		=======================================================================
*/

		class tar {
	// Unprocessed Archive Information
			var $filename;
	var $isGzipped;
	var $tar_file;

	// Processed Archive Information
			var $files;
	var $directories;
	var $numFiles;
	var $numDirectories;


	// Class Constructor -- Does nothing...
			function tar() {
		return true;
			}


	// Computes the unsigned Checksum of a file's header
	// to try to ensure valid file
	// PRIVATE ACCESS FUNCTION
			function __computeUnsignedChecksum($bytestring) {
		for($i=0; $i<512; $i++)
			$unsigned_chksum += ord($bytestring[$i]);
		for($i=0; $i<8; $i++)
			$unsigned_chksum -= ord($bytestring[148 + $i]);
		$unsigned_chksum += ord(" ") * 8;

		return $unsigned_chksum;
			}


	// Converts a NULL padded string to a non-NULL padded string
	// PRIVATE ACCESS FUNCTION
			function __parseNullPaddedString($string) {
		$position = strpos($string,chr(0));
		return substr($string,0,$position);
			}


	// This function parses the current TAR file
	// PRIVATE ACCESS FUNCTION
			function __parseTar() {
		// Read Files from archive
				$tar_length = strlen($this->tar_file);
		$main_offset = 0;
		while($main_offset < $tar_length) {
			// If we read a block of 512 nulls, we are at the end of the archive
					if(substr($this->tar_file,$main_offset,512) == str_repeat(chr(0),512))
					break;

			// Parse file name
					$file_name		= $this->__parseNullPaddedString(substr($this->tar_file,$main_offset,100));

			// Parse the file mode
					$file_mode		= substr($this->tar_file,$main_offset + 100,8);

			// Parse the file user ID
					$file_uid		= octdec(substr($this->tar_file,$main_offset + 108,8));

			// Parse the file group ID
					$file_gid		= octdec(substr($this->tar_file,$main_offset + 116,8));

			// Parse the file size
					$file_size		= octdec(substr($this->tar_file,$main_offset + 124,12));

			// Parse the file update time - unix timestamp format
					$file_time		= octdec(substr($this->tar_file,$main_offset + 136,12));

			// Parse Checksum
					$file_chksum		= octdec(substr($this->tar_file,$main_offset + 148,6));

			// Parse user name
					$file_uname		= $this->__parseNullPaddedString(substr($this->tar_file,$main_offset + 265,32));

			// Parse Group name
					$file_gname		= $this->__parseNullPaddedString(substr($this->tar_file,$main_offset + 297,32));

			// Make sure our file is valid
					if($this->__computeUnsignedChecksum(substr($this->tar_file,$main_offset,512)) != $file_chksum)
					return false;

			// Parse File Contents
					$file_contents		= substr($this->tar_file,$main_offset + 512,$file_size);

			/*	### Unused Header Information ###
					$activeFile["typeflag"]		= substr($this->tar_file,$main_offset + 156,1);
			$activeFile["linkname"]		= substr($this->tar_file,$main_offset + 157,100);
			$activeFile["magic"]		= substr($this->tar_file,$main_offset + 257,6);
			$activeFile["version"]		= substr($this->tar_file,$main_offset + 263,2);
			$activeFile["devmajor"]		= substr($this->tar_file,$main_offset + 329,8);
			$activeFile["devminor"]		= substr($this->tar_file,$main_offset + 337,8);
			$activeFile["prefix"]		= substr($this->tar_file,$main_offset + 345,155);
			$activeFile["endheader"]	= substr($this->tar_file,$main_offset + 500,12);
			*/

					if($file_size > 0) {
				// Increment number of files
						$this->numFiles++;

				// Create us a new file in our array
						$activeFile = &$this->files[];

				// Asign Values
						$activeFile["name"]		= $file_name;
				$activeFile["mode"]		= $file_mode;
				$activeFile["size"]		= $file_size;
				$activeFile["time"]		= $file_time;
				$activeFile["user_id"]		= $file_uid;
				$activeFile["group_id"]		= $file_gid;
				$activeFile["user_name"]	= $file_uname;
				$activeFile["group_name"]	= $file_gname;
				$activeFile["checksum"]		= $file_chksum;
				$activeFile["file"]		= $file_contents;

					} else {
				// Increment number of directories
						$this->numDirectories++;

				// Create a new directory in our array
						$activeDir = &$this->directories[];

				// Assign values
						$activeDir["name"]		= $file_name;
				$activeDir["mode"]		= $file_mode;
				$activeDir["time"]		= $file_time;
				$activeDir["user_id"]		= $file_uid;
				$activeDir["group_id"]		= $file_gid;
				$activeDir["user_name"]		= $file_uname;
				$activeDir["group_name"]	= $file_gname;
				$activeDir["checksum"]		= $file_chksum;
					}

			// Move our offset the number of blocks we have processed
					$main_offset += 512 + (ceil($file_size / 512) * 512);
		}

		return true;
			}


	// Read a non gzipped tar file in for processing
	// PRIVATE ACCESS FUNCTION
			function __readTar($filename='') {
		// Set the filename to load
				if(!$filename)
				$filename = $this->filename;

		// Read in the TAR file
				$fp = fopen($filename,"rb");
		$this->tar_file = fread($fp,filesize($filename));
		fclose($fp);

		if($this->tar_file[0] == chr(31) && $this->tar_file[1] == chr(139) && $this->tar_file[2] == chr(8)) {
			if(!function_exists("gzinflate"))
				return false;

			$this->isGzipped = TRUE;

			$this->tar_file = gzinflate(substr($this->tar_file,10,-4));
		}

		// Parse the TAR file
				$this->__parseTar();

		return true;
			}


	// Generates a TAR file from the processed data
	// PRIVATE ACCESS FUNCTION
			function __generateTAR() {
		// Clear any data currently in $this->tar_file	
				unset($this->tar_file);

		// Generate Records for each directory, if we have directories
				if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				unset($header);

				// Generate tar header for this directory
				// Filename, Permissions, UID, GID, size, Time, checksum, typeflag, linkname, magic, version, user name, group name, devmajor, devminor, prefix, end
						$header .= str_pad($information["name"],100,chr(0));
				$header .= str_pad(decoct($information["mode"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["user_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["group_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct(0),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["time"]),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_repeat(" ",8);
				$header .= "5";
				$header .= str_repeat(chr(0),100);
				$header .= str_pad("ustar",6,chr(32));
				$header .= chr(32) . chr(0);
				$header .= str_pad("",32,chr(0));
				$header .= str_pad("",32,chr(0));
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),155);
				$header .= str_repeat(chr(0),12);

				// Compute header checksum
						$checksum = str_pad(decoct($this->__computeUnsignedChecksum($header)),6,"0",STR_PAD_LEFT);
				for($i=0; $i<6; $i++) {
					$header[(148 + $i)] = substr($checksum,$i,1);
				}
				$header[154] = chr(0);
				$header[155] = chr(32);

				// Add new tar formatted data to tar file contents
						$this->tar_file .= $header;
			}
				}

		// Generate Records for each file, if we have files (We should...)
				if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				unset($header);

				// Generate the TAR header for this file
				// Filename, Permissions, UID, GID, size, Time, checksum, typeflag, linkname, magic, version, user name, group name, devmajor, devminor, prefix, end
						$header .= str_pad($information["name"],100,chr(0));
				$header .= str_pad(decoct($information["mode"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["user_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["group_id"]),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["size"]),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct($information["time"]),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_repeat(" ",8);
				$header .= "0";
				$header .= str_repeat(chr(0),100);
				$header .= str_pad("ustar",6,chr(32));
				$header .= chr(32) . chr(0);
				$header .= str_pad($information["user_name"],32,chr(0));	// How do I get a file's user name from PHP?
						$header .= str_pad($information["group_name"],32,chr(0));	// How do I get a file's group name from PHP?
								$header .= str_repeat(chr(0),8);
								$header .= str_repeat(chr(0),8);
								$header .= str_repeat(chr(0),155);
								$header .= str_repeat(chr(0),12);

				// Compute header checksum
						$checksum = str_pad(decoct($this->__computeUnsignedChecksum($header)),6,"0",STR_PAD_LEFT);
				for($i=0; $i<6; $i++) {
					$header[(148 + $i)] = substr($checksum,$i,1);
				}
				$header[154] = chr(0);
				$header[155] = chr(32);

				// Pad file contents to byte count divisible by 512
						$file_contents = str_pad($information["file"],(ceil($information["size"] / 512) * 512),chr(0));

				// Add new tar formatted data to tar file contents
						$this->tar_file .= $header . $file_contents;
			}
				}

		// Add 512 bytes of NULLs to designate EOF
				$this->tar_file .= str_repeat(chr(0),512);

		return true;
			}


	// Open a TAR file
			function openTAR($filename) {
		// Clear any values from previous tar archives
				unset($this->filename);
		unset($this->isGzipped);
		unset($this->tar_file);
		unset($this->files);
		unset($this->directories);
		unset($this->numFiles);
		unset($this->numDirectories);

		// If the tar file doesn't exist...
				if(!file_exists($filename))
				return false;

		$this->filename = $filename;

		// Parse this file
				$this->__readTar();

		return true;
			}


	// Appends a tar file to the end of the currently opened tar file
			function appendTar($filename) {
		// If the tar file doesn't exist...
				if(!file_exists($filename))
				return false;

		$this->__readTar($filename);

		return true;
			}


	// Retrieves information about a file in the current tar archive
			function getFile($filename) {
		if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				if($information["name"] == $filename)
					return $information;
			}
		}

		return false;
			}


	// Retrieves information about a directory in the current tar archive
			function getDirectory($dirname) {
		if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				if($information["name"] == $dirname)
					return $information;
			}
		}

		return false;
			}


	// Check if this tar archive contains a specific file
			function containsFile($filename) {
		if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				if($information["name"] == $filename)
					return true;
			}
		}

		return false;
			}


	// Check if this tar archive contains a specific directory
			function containsDirectory($dirname) {
		if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				if($information["name"] == $dirname)
					return true;
			}
		}

		return false;
			}


	// Add a directory to this tar archive
			function addDirectory($dirname) {
		if(!file_exists($dirname))
			return false;

		// Get directory information
				$file_information = stat($dirname);

		// Add directory to processed data
				$this->numDirectories++;
		$activeDir		= &$this->directories[];
		$activeDir["name"]	= $dirname;
		$activeDir["mode"]	= $file_information["mode"];
		$activeDir["time"]	= $file_information["time"];
		$activeDir["user_id"]	= $file_information["uid"];
		$activeDir["group_id"]	= $file_information["gid"];
		$activeDir["checksum"]	= $checksum;

		return true;
			}


	// Add a file to the tar archive
			function addFile($filename) {
		// Make sure the file we are adding exists!
				if(!file_exists($filename))
				return false;

		// Make sure there are no other files in the archive that have this same filename
				if($this->containsFile($filename))
				return false;

		// Get file information
				$file_information = stat($filename);

		// Read in the file's contents
				$fp = fopen($filename,"rb");
		$file_contents = fread($fp,filesize($filename));
		fclose($fp);

		// Add file to processed data
				$this->numFiles++;
		$activeFile			= &$this->files[];
		$activeFile["name"]		= $filename;
		$activeFile["mode"]		= $file_information["mode"];
		$activeFile["user_id"]		= $file_information["uid"];
		$activeFile["group_id"]		= $file_information["gid"];
		$activeFile["size"]		= $file_information["size"];
		$activeFile["time"]		= $file_information["mtime"];
		$activeFile["checksum"]		= $checksum;
		$activeFile["user_name"]	= "";
		$activeFile["group_name"]	= "";
		$activeFile["file"]		= $file_contents;

		return true;
			}


	// Remove a file from the tar archive
			function removeFile($filename) {
		if($this->numFiles > 0) {
			foreach($this->files as $key => $information) {
				if($information["name"] == $filename) {
					$this->numFiles--;
					unset($this->files[$key]);
					return true;
				}
			}
		}

		return false;
			}


	// Remove a directory from the tar archive
			function removeDirectory($dirname) {
		if($this->numDirectories > 0) {
			foreach($this->directories as $key => $information) {
				if($information["name"] == $dirname) {
					$this->numDirectories--;
					unset($this->directories[$key]);
					return true;
				}
			}
		}

		return false;
			}


	// Write the currently loaded tar archive to disk
			function saveTar() {
		if(!$this->filename)
			return false;

		// Write tar to current file using specified gzip compression
				$this->toTar($this->filename,$this->isGzipped);

		return true;
			}


	// Saves tar archive to a different file than the current file
			function toTar($filename,$useGzip) {
		if(!$filename)
			return false;

		// Encode processed files into TAR file format
				$this->__generateTar();

		// GZ Compress the data if we need to
				if($useGzip) {
			// Make sure we have gzip support
					if(!function_exists("gzencode"))
					return false;

			$file = gzencode($this->tar_file);
				} else {
					$file = $this->tar_file;
				}

		// Write the TAR file
				$fp = fopen($filename,"wb");
		fwrite($fp,$file);
		fclose($fp);

		return true;
			}
		}





/*--------------------------------------------------
| TAR/GZIP/BZIP2/ZIP ARCHIVE CLASSES 2.1
| By Devin Doucette
| Copyright (c) 2005 Devin Doucette
| Email: darksnoopy@shaw.ca
+--------------------------------------------------
| Email bugs/suggestions to darksnoopy@shaw.ca
+--------------------------------------------------
| This script has been created and released under
| the GNU GPL and is free to use and redistribute
| only if this copyright statement is not removed
+--------------------------------------------------*/

class archive
{
	function archive($name)
	{
		$this->options = array (
			'basedir' => ".",
			'name' => $name,
			'prepend' => "",
			'inmemory' => 0,
			'overwrite' => 0,
			'recurse' => 1,
			'storepaths' => 1,
			'followlinks' => 0,
			'level' => 3,
			'method' => 1,
			'sfx' => "",
			'type' => "",
			'comment' => ""
		);
		$this->files = array ();
		$this->exclude = array ();
		$this->storeonly = array ();
		$this->error = array ();
	}

	function set_options($options)
	{
		foreach ($options as $key => $value)
			$this->options[$key] = $value;
		if (!empty ($this->options['basedir']))
		{
			$this->options['basedir'] = str_replace("\\", "/", $this->options['basedir']);
			$this->options['basedir'] = preg_replace("/\/+/", "/", $this->options['basedir']);
			$this->options['basedir'] = preg_replace("/\/$/", "", $this->options['basedir']);
		}
		if (!empty ($this->options['name']))
		{
			$this->options['name'] = str_replace("\\", "/", $this->options['name']);
			$this->options['name'] = preg_replace("/\/+/", "/", $this->options['name']);
		}
		if (!empty ($this->options['prepend']))
		{
			$this->options['prepend'] = str_replace("\\", "/", $this->options['prepend']);
			$this->options['prepend'] = preg_replace("/^(\.*\/+)+/", "", $this->options['prepend']);
			$this->options['prepend'] = preg_replace("/\/+/", "/", $this->options['prepend']);
			$this->options['prepend'] = preg_replace("/\/$/", "", $this->options['prepend']) . "/";
		}
	}

	function create_archive()
	{
		$this->make_list();

		if ($this->options['inmemory'] == 0)
		{
			$pwd = getcwd();
			chdir($this->options['basedir']);
			if ($this->options['overwrite'] == 0 && file_exists($this->options['name'] . ($this->options['type'] == "gzip" || $this->options['type'] == "bzip" ? ".tmp" : "")))
			{
				$this->error[] = "File {$this->options['name']} already exists.";
				chdir($pwd);
				return 0;
			}
			else if ($this->archive = @fopen($this->options['name'] . ($this->options['type'] == "gzip" || $this->options['type'] == "bzip" ? ".tmp" : ""), "wb+"))
				chdir($pwd);
			else
			{
				$this->error[] = "Could not open {$this->options['name']} for writing.";
				chdir($pwd);
				return 0;
			}
		}
		else
			$this->archive = "";

		switch ($this->options['type'])
		{
			case "zip":
				if (!$this->create_zip())
				{
					$this->error[] = "Could not create zip file.";
					return 0;
				}
				break;
			case "bzip":
				if (!$this->create_tar())
				{
					$this->error[] = "Could not create tar file.";
					return 0;
				}
				if (!$this->create_bzip())
				{
					$this->error[] = "Could not create bzip2 file.";
					return 0;
				}
				break;
			case "gzip":
				if (!$this->create_tar())
				{
					$this->error[] = "Could not create tar file.";
					return 0;
				}
				if (!$this->create_gzip())
				{
					$this->error[] = "Could not create gzip file.";
					return 0;
				}
				break;
			case "tar":
				if (!$this->create_tar())
				{
					$this->error[] = "Could not create tar file.";
					return 0;
				}
		}

		if ($this->options['inmemory'] == 0)
		{
			fclose($this->archive);
			if ($this->options['type'] == "gzip" || $this->options['type'] == "bzip")
				unlink($this->options['basedir'] . "/" . $this->options['name'] . ".tmp");
		}
	}

	function add_data($data)
	{
		if ($this->options['inmemory'] == 0)
			fwrite($this->archive, $data);
		else
			$this->archive .= $data;
	}

	function make_list()
	{
		if (!empty ($this->exclude))
			foreach ($this->files as $key => $value)
				foreach ($this->exclude as $current)
					if ($value['name'] == $current['name'])
						unset ($this->files[$key]);
		if (!empty ($this->storeonly))
			foreach ($this->files as $key => $value)
				foreach ($this->storeonly as $current)
					if ($value['name'] == $current['name'])
						$this->files[$key]['method'] = 0;
		unset ($this->exclude, $this->storeonly);
	}

	function add_files($list)
	{
		$temp = $this->list_files($list);
		foreach ($temp as $current)
			$this->files[] = $current;
	}

	function exclude_files($list)
	{
		$temp = $this->list_files($list);
		foreach ($temp as $current)
			$this->exclude[] = $current;
	}

	function store_files($list)
	{
		$temp = $this->list_files($list);
		foreach ($temp as $current)
			$this->storeonly[] = $current;
	}

	function list_files($list)
	{
		if (!is_array ($list))
		{
			$temp = $list;
			$list = array ($temp);
			unset ($temp);
		}

		$files = array ();

		$pwd = getcwd();
		chdir($this->options['basedir']);

		foreach ($list as $current)
		{
			$current = str_replace("\\", "/", $current);
			$current = preg_replace("/\/+/", "/", $current);
			$current = preg_replace("/\/$/", "", $current);
			if (strstr($current, "*"))
			{
				$regex = preg_replace("/([\\\^\$\.\[\]\|\(\)\?\+\{\}\/])/", "\\\\\\1", $current);
				$regex = str_replace("*", ".*", $regex);
				$dir = strstr($current, "/") ? substr($current, 0, strrpos($current, "/")) : ".";
				$temp = $this->parse_dir($dir);
				foreach ($temp as $current2)
					if (preg_match("/^{$regex}$/i", $current2['name']))
						$files[] = $current2;
				unset ($regex, $dir, $temp, $current);
			}
			else if (@is_dir($current))
			{
				$temp = $this->parse_dir($current);
				foreach ($temp as $file)
					$files[] = $file;
				unset ($temp, $file);
			}
			else if (@file_exists($current))
				$files[] = array ('name' => $current, 'name2' => $this->options['prepend'] .
						preg_replace("/(\.+\/+)+/", "", ($this->options['storepaths'] == 0 && strstr($current, "/")) ?
								substr($current, strrpos($current, "/") + 1) : $current),
									   'type' => @is_link($current) && $this->options['followlinks'] == 0 ? 2 : 0,
											   'ext' => substr($current, strrpos($current, ".")), 'stat' => stat($current));
		}

		chdir($pwd);

		unset ($current, $pwd);

		usort($files, array ("archive", "sort_files"));

		return $files;
	}

	function parse_dir($dirname)
	{
		if ($this->options['storepaths'] == 1 && !preg_match("/^(\.+\/*)+$/", $dirname))
			$files = array (array ('name' => $dirname, 'name2' => $this->options['prepend'] .
					preg_replace("/(\.+\/+)+/", "", ($this->options['storepaths'] == 0 && strstr($dirname, "/")) ?
							substr($dirname, strrpos($dirname, "/") + 1) : $dirname), 'type' => 5, 'stat' => stat($dirname)));
		else
			$files = array ();
		$dir = @opendir($dirname);

		while ($file = @readdir($dir))
		{
			$fullname = $dirname . "/" . $file;
			if ($file == "." || $file == "..")
				continue;
			else if (@is_dir($fullname))
			{
				if (empty ($this->options['recurse']))
					continue;
				$temp = $this->parse_dir($fullname);
				foreach ($temp as $file2)
					$files[] = $file2;
			}
			else if (@file_exists($fullname))
				$files[] = array ('name' => $fullname, 'name2' => $this->options['prepend'] .
						preg_replace("/(\.+\/+)+/", "", ($this->options['storepaths'] == 0 && strstr($fullname, "/")) ?
								substr($fullname, strrpos($fullname, "/") + 1) : $fullname),
									   'type' => @is_link($fullname) && $this->options['followlinks'] == 0 ? 2 : 0,
											   'ext' => substr($file, strrpos($file, ".")), 'stat' => stat($fullname));
		}

		@closedir($dir);

		return $files;
	}

	function sort_files($a, $b)
	{
		if ($a['type'] != $b['type'])
			if ($a['type'] == 5 || $b['type'] == 2)
				return -1;
		else if ($a['type'] == 2 || $b['type'] == 5)
			return 1;
		else if ($a['type'] == 5)
			return strcmp(strtolower($a['name']), strtolower($b['name']));
		else if ($a['ext'] != $b['ext'])
			return strcmp($a['ext'], $b['ext']);
		else if ($a['stat'][7] != $b['stat'][7])
			return $a['stat'][7] > $b['stat'][7] ? -1 : 1;
		else
			return strcmp(strtolower($a['name']), strtolower($b['name']));
		return 0;
	}

	function download_file()
	{
		if ($this->options['inmemory'] == 0)
		{
			$this->error[] = "Can only use download_file() if archive is in memory. Redirect to file otherwise, it is faster.";
			return;
		}
		switch ($this->options['type'])
		{
			case "zip":
				header("Content-Type: application/zip");
				break;
			case "bzip":
				header("Content-Type: application/x-bzip2");
				break;
			case "gzip":
				header("Content-Type: application/x-gzip");
				break;
			case "tar":
				header("Content-Type: application/x-tar");
		}
		$header = "Content-Disposition: attachment; filename=\"";
		$header .= strstr($this->options['name'], "/") ? substr($this->options['name'], strrpos($this->options['name'], "/") + 1) : $this->options['name'];
		$header .= "\"";
		header($header);
		header("Content-Length: " . strlen($this->archive));
		header("Content-Transfer-Encoding: binary");
		header("Cache-Control: no-cache, must-revalidate, max-age=60");
		header("Expires: Sat, 01 Jan 2000 12:00:00 GMT");
		print($this->archive);
	}
}

// class tar_file extends archive
// {
// 	function tar_file($name)
// 	{
// 		$this->archive($name);
// 		$this->options['type'] = "tar";
// 	}
// 
// 	function create_tar()
// 	{
// 		$pwd = getcwd();
// 		chdir($this->options['basedir']);
// 
// 		foreach ($this->files as $current)
// 		{
// 			if ($current['name'] == $this->options['name'])
// 				continue;
// 			if (strlen($current['name2']) > 99)
// 			{
// 				$path = substr($current['name2'], 0, strpos($current['name2'], "/", strlen($current['name2']) - 100) + 1);
// 				$current['name2'] = substr($current['name2'], strlen($path));
// 				if (strlen($path) > 154 || strlen($current['name2']) > 99)
// 				{
// 					$this->error[] = "Could not add {$path}{$current['name2']} to archive because the filename is too long.";
// 					continue;
// 				}
// 			}
// 			$block = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12", $current['name2'], sprintf("%07o", 
// 						  $current['stat'][2]), sprintf("%07o", $current['stat'][4]), sprintf("%07o", $current['stat'][5]), 
// 								  sprintf("%011o", $current['type'] == 2 ? 0 : $current['stat'][7]), sprintf("%011o", $current['stat'][9]), 
// 										  "        ", $current['type'], $current['type'] == 2 ? @readlink($current['name']) : "", "ustar ", " ", 
// 												  "Unknown", "Unknown", "", "", !empty ($path) ? $path : "", "");
// 
// 			$checksum = 0;
// 			for ($i = 0; $i < 512; $i++)
// 				$checksum += ord(substr($block, $i, 1));
// 			$checksum = pack("a8", sprintf("%07o", $checksum));
// 			$block = substr_replace($block, $checksum, 148, 8);
// 
// 			if ($current['type'] == 2 || $current['stat'][7] == 0)
// 				$this->add_data($block);
// 			else if ($fp = @fopen($current['name'], "rb"))
// 			{
// 				$this->add_data($block);
// 				while ($temp = fread($fp, 1048576))
// 					$this->add_data($temp);
// 				if ($current['stat'][7] % 512 > 0)
// 				{
// 					$temp = "";
// 					for ($i = 0; $i < 512 - $current['stat'][7] % 512; $i++)
// 						$temp .= "\0";
// 					$this->add_data($temp);
// 				}
// 				fclose($fp);
// 			}
// 			else
// 				$this->error[] = "Could not open file {$current['name']} for reading. It was not added.";
// 		}
// 
// 		$this->add_data(pack("a1024", ""));
// 
// 		chdir($pwd);
// 
// 		return 1;
// 	}
// 
// 	function extract_files()
// 	{
// 		$pwd = getcwd();
// 		chdir($this->options['basedir']);
// 
// 		if ($fp = $this->open_archive())
// 		{
// 			if ($this->options['inmemory'] == 1)
// 				$this->files = array ();
// 
// 			while ($block = fread($fp, 512))
// 			{
// 				$temp = unpack("a100name/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100symlink/a6magic/a2temp/a32temp/a32temp/a8temp/a8temp/a155prefix/a12temp", $block);
// 				$file = array (
// 							   'name' => $temp['prefix'] . $temp['name'],
// 		  'stat' => array (
// 						   2 => $temp['mode'],
// 		 4 => octdec($temp['uid']),
// 					 5 => octdec($temp['gid']),
// 								 7 => octdec($temp['size']),
// 											 9 => octdec($temp['mtime']),
// 						  ),
// 		'checksum' => octdec($temp['checksum']),
// 							 'type' => $temp['type'],
// 		'magic' => $temp['magic'],
// 							  );
// 				if ($file['checksum'] == 0x00000000)
// 					break;
// 				else if (substr($file['magic'], 0, 5) != "ustar")
// 				{
// 					$this->error[] = "This script does not support extracting this type of tar file.";
// 					break;
// 				}
// 				$block = substr_replace($block, "        ", 148, 8);
// 				$checksum = 0;
// 				for ($i = 0; $i < 512; $i++)
// 					$checksum += ord(substr($block, $i, 1));
// 				if ($file['checksum'] != $checksum)
// 					$this->error[] = "Could not extract from {$this->options['name']}, it is corrupt.";
// 
// 				if ($this->options['inmemory'] == 1)
// 				{
// 					$file['data'] = fread($fp, $file['stat'][7]);
// 					fread($fp, (512 - $file['stat'][7] % 512) == 512 ? 0 : (512 - $file['stat'][7] % 512));
// 					unset ($file['checksum'], $file['magic']);
// 					$this->files[] = $file;
// 				}
// 				else if ($file['type'] == 5)
// 				{
// 					if (!is_dir($file['name']))
// 					{
// //						mkdir($file['name'], $file['stat'][2]);
// 						mkdir($file['name']);
// 					}
// 				}
// 				else if ($this->options['overwrite'] == 0 && file_exists($file['name']))
// 				{
// 					$this->error[] = "{$file['name']} already exists.";
// 					continue;
// 				}
// 				else if ($file['type'] == 2)
// 				{
// 					symlink($temp['symlink'], $file['name']);
// 					chmod($file['name'], $file['stat'][2]);
// 				}
// 				else if ($new = @fopen($file['name'], "wb"))
// 				{
// 					fwrite($new, fread($fp, $file['stat'][7]));
// 					fread($fp, (512 - $file['stat'][7] % 512) == 512 ? 0 : (512 - $file['stat'][7] % 512));
// 					fclose($new);
// //					chmod($file['name'], $file['stat'][2]);
// 				}
// 				else
// 				{
// 					$this->error[] = "Could not open {$file['name']} for writing.";
// 					continue;
// 				}
// //				chown($file['name'], $file['stat'][4]);
// //				chgrp($file['name'], $file['stat'][5]);
// //				touch($file['name'], $file['stat'][9]);
// 				unset ($file);
// 			}
// 		}
// 		else
// 			$this->error[] = "Could not open file {$this->options['name']}";
// 
// 		chdir($pwd);
// 	}
// 
// 	function open_archive()
// 	{
// 		return @fopen($this->options['name'], "rb");
// 	}
// }
// 
// class gzip_file extends tar_file
// {
// 	function gzip_file($name)
// 	{
// 		$this->tar_file($name);
// 		$this->options['type'] = "gzip";
// 	}
// 
// 	function create_gzip()
// 	{
// 		if ($this->options['inmemory'] == 0)
// 		{
// 			$pwd = getcwd();
// 			chdir($this->options['basedir']);
// 			if ($fp = gzopen($this->options['name'], "wb{$this->options['level']}"))
// 			{
// 				fseek($this->archive, 0);
// 				while ($temp = fread($this->archive, 1048576))
// 					gzwrite($fp, $temp);
// 				gzclose($fp);
// 				chdir($pwd);
// 			}
// 			else
// 			{
// 				$this->error[] = "Could not open {$this->options['name']} for writing.";
// 				chdir($pwd);
// 				return 0;
// 			}
// 		}
// 		else
// 			$this->archive = gzencode($this->archive, $this->options['level']);
// 
// 		return 1;
// 	}
// 
// 	function open_archive()
// 	{
// 		return @gzopen($this->options['name'], "rb");
// 	}
// }
// 
// class bzip_file extends tar_file
// {
// 	function bzip_file($name)
// 	{
// 		$this->tar_file($name);
// 		$this->options['type'] = "bzip";
// 	}
// 
// 	function create_bzip()
// 	{
// 		if ($this->options['inmemory'] == 0)
// 		{
// 			$pwd = getcwd();
// 			chdir($this->options['basedir']);
// 			if ($fp = bzopen($this->options['name'], "wb"))
// 			{
// 				fseek($this->archive, 0);
// 				while ($temp = fread($this->archive, 1048576))
// 					bzwrite($fp, $temp);
// 				bzclose($fp);
// 				chdir($pwd);
// 			}
// 			else
// 			{
// 				$this->error[] = "Could not open {$this->options['name']} for writing.";
// 				chdir($pwd);
// 				return 0;
// 			}
// 		}
// 		else
// 			$this->archive = bzcompress($this->archive, $this->options['level']);
// 
// 		return 1;
// 	}
// 
// 	function open_archive()
// 	{
// 		return @bzopen($this->options['name'], "rb");
// 	}
// }
// 
// class zip_file extends archive
// {
// 	function zip_file($name)
// 	{
// 		$this->archive($name);
// 		$this->options['type'] = "zip";
// 	}
// 
// 	function create_zip()
// 	{
// 		$files = 0;
// 		$offset = 0;
// 		$central = "";
// 
// 		if (!empty ($this->options['sfx']))
// 			if ($fp = @fopen($this->options['sfx'], "rb"))
// 		{
// 			$temp = fread($fp, filesize($this->options['sfx']));
// 			fclose($fp);
// 			$this->add_data($temp);
// 			$offset += strlen($temp);
// 			unset ($temp);
// 		}
// 		else
// 			$this->error[] = "Could not open sfx module from {$this->options['sfx']}.";
// 
// 		$pwd = getcwd();
// 		chdir($this->options['basedir']);
// 
// 		foreach ($this->files as $current)
// 		{
// 			if ($current['name'] == $this->options['name'])
// 				continue;
// 
// 			$timedate = explode(" ", date("Y n j G i s", $current['stat'][9]));
// 			$timedate = ($timedate[0] - 1980 << 25) | ($timedate[1] << 21) | ($timedate[2] << 16) |
// 					($timedate[3] << 11) | ($timedate[4] << 5) | ($timedate[5]);
// 
// 			$block = pack("VvvvV", 0x04034b50, 0x000A, 0x0000, (isset($current['method']) || $this->options['method'] == 0) ? 0x0000 : 0x0008, $timedate);
// 
// 			if ($current['stat'][7] == 0 && $current['type'] == 5)
// 			{
// 				$block .= pack("VVVvv", 0x00000000, 0x00000000, 0x00000000, strlen($current['name2']) + 1, 0x0000);
// 				$block .= $current['name2'] . "/";
// 				$this->add_data($block);
// 				$central .= pack("VvvvvVVVVvvvvvVV", 0x02014b50, 0x0014, $this->options['method'] == 0 ? 0x0000 : 0x000A, 0x0000,
// 								 (isset($current['method']) || $this->options['method'] == 0) ? 0x0000 : 0x0008, $timedate,
// 								  0x00000000, 0x00000000, 0x00000000, strlen($current['name2']) + 1, 0x0000, 0x0000, 0x0000, 0x0000, $current['type'] == 5 ? 0x00000010 : 0x00000000, $offset);
// 				$central .= $current['name2'] . "/";
// 				$files++;
// 				$offset += (31 + strlen($current['name2']));
// 			}
// 			else if ($current['stat'][7] == 0)
// 			{
// 				$block .= pack("VVVvv", 0x00000000, 0x00000000, 0x00000000, strlen($current['name2']), 0x0000);
// 				$block .= $current['name2'];
// 				$this->add_data($block);
// 				$central .= pack("VvvvvVVVVvvvvvVV", 0x02014b50, 0x0014, $this->options['method'] == 0 ? 0x0000 : 0x000A, 0x0000,
// 								 (isset($current['method']) || $this->options['method'] == 0) ? 0x0000 : 0x0008, $timedate,
// 								  0x00000000, 0x00000000, 0x00000000, strlen($current['name2']), 0x0000, 0x0000, 0x0000, 0x0000, $current['type'] == 5 ? 0x00000010 : 0x00000000, $offset);
// 				$central .= $current['name2'];
// 				$files++;
// 				$offset += (30 + strlen($current['name2']));
// 			}
// 			else if ($fp = @fopen($current['name'], "rb"))
// 			{
// 				$temp = fread($fp, $current['stat'][7]);
// 				fclose($fp);
// 				$crc32 = crc32($temp);
// 				if (!isset($current['method']) && $this->options['method'] == 1)
// 				{
// 					$temp = gzcompress($temp, $this->options['level']);
// 					$size = strlen($temp) - 6;
// 					$temp = substr($temp, 2, $size);
// 				}
// 				else
// 					$size = strlen($temp);
// 				$block .= pack("VVVvv", $crc32, $size, $current['stat'][7], strlen($current['name2']), 0x0000);
// 				$block .= $current['name2'];
// 				$this->add_data($block);
// 				$this->add_data($temp);
// 				unset ($temp);
// 				$central .= pack("VvvvvVVVVvvvvvVV", 0x02014b50, 0x0014, $this->options['method'] == 0 ? 0x0000 : 0x000A, 0x0000,
// 								 (isset($current['method']) || $this->options['method'] == 0) ? 0x0000 : 0x0008, $timedate,
// 								  $crc32, $size, $current['stat'][7], strlen($current['name2']), 0x0000, 0x0000, 0x0000, 0x0000, 0x00000000, $offset);
// 				$central .= $current['name2'];
// 				$files++;
// 				$offset += (30 + strlen($current['name2']) + $size);
// 			}
// 			else
// 				$this->error[] = "Could not open file {$current['name']} for reading. It was not added.";
// 		}
// 
// 		$this->add_data($central);
// 
// 		$this->add_data(pack("VvvvvVVv", 0x06054b50, 0x0000, 0x0000, $files, $files, strlen($central), $offset,
// 						!empty ($this->options['comment']) ? strlen($this->options['comment']) : 0x0000));
// 
// 		if (!empty ($this->options['comment']))
// 			$this->add_data($this->options['comment']);
// 
// 		chdir($pwd);
// 
// 		return 1;
// 	}
// }





/*
untar.class.php
Version 1.0

Part of the PHP class collection
http://www.sourceforge.net/projects/php-classes/

Written by: Dennis Wronka
License: LGPL
*/
class untar
{
	private $filename;
	private $filelist;

	public function __construct($filename)
	{
		$this->filename=$filename;
		$this->createfilelist();
	}

	private function createfilelist()
	{
		$this->filelist=array();
		$tarfile=fopen($this->filename,'r');
		if ($tarfile==false)
		{
			return false;
		}
		$datainfo='';
		$data='';
		while (!feof($tarfile))
		{
			$readdata=fread($tarfile,512);
			if (substr($readdata,257,5)=='ustar')
			{
				$offset=ftell($tarfile);
				$filename='';
				$position=0;
				$filename=substr($readdata,0,strpos($readdata,chr(0)));
				$permissions=substr($readdata,100,8);
				$filesize=octdec(substr($readdata,124,12));
				$indicator=substr($readdata,156,1);
				$fileuser=substr($readdata,265,strpos($readdata,chr(0),265)-265);
				$filegroup=substr($readdata,297,strpos($readdata,chr(0),297)-297);
				if ($indicator==5)
				{
					$filetype='directory';
					$offset=-1;
				}
				else
				{
					$filetype='file';
				}
				$this->filelist[]=array('filename'=>$filename,'filetype'=>$filetype,'offset'=>$offset,'filesize'=>$filesize,'user'=>$fileuser,'group'=>$filegroup,'permissions'=>$permissions);
			}
		}
		fclose($tarfile);
	}

	public function getfilelist()
	{
		return $this->filelist;
	}

	public function extract($filename)
	{
		$found=false;
		for ($x=0;$x<count($this->filelist);$x++)
		{
			if (in_array($filename,$this->filelist[$x]))
			{
				$found=$x;
			}
		}
		if ($found===false)
		{
			return false;
		}
		if ($this->filelist[$found]['filetype']=='directory')
		{
			return 2;
		}
		$tarfile=@fopen($this->filename,'r');
		if ($tarfile==false)
		{
			return false;
		}
		fseek($tarfile,$this->filelist[$found]['offset']);
		$data=fread($tarfile,$this->filelist[$found]['filesize']);
		fclose($tarfile);
		return $data;
	}
}

class Commander
{
	//TODO:
	//	chyba c. 01:
	//    1. zmazem nejaky subor
	//    2. iny subor -> download
	//    3. back
	//    Warning: Unknown file type (0) in d:\matmas\matmascmd.php on line 1115 +5
	//	  Warning: OpenDir: Invalid argument (errno 22) in d:\matmas\matmascmd.php on line 1298 +5
	//	  Fatal error: Call to a member function on a non-object in d:\matmas\matmascmd.php on line 1309 +5
	
	var $version = "5.11.2007";
	
	function Commander()
	{
		if (IsSet($_REQUEST["l"]))
		{
			$this->leftPanelItems = $this->StripSlashesArray($_REQUEST["l"]);
		}
		if (IsSet($_REQUEST["r"]))
		{
			$this->rightPanelItems = $this->StripSlashesArray($_REQUEST["r"]);
		}
		
		if (IsSet($_REQUEST["lpwd"]))
		{
			$_SESSION["left_pwd"] = $this->StripSlashes($_REQUEST["lpwd"]);
		}
		if (!IsSet($_SESSION["left_pwd"]))
		{
			$_SESSION["left_pwd"] = $this->UpDir($this->GetPathTranslated());
		}
		$this->pwd["left"] = $_SESSION["left_pwd"];
		
		if (IsSet($_REQUEST["rpwd"]))
		{
			$_SESSION["right_pwd"] = $this->StripSlashes($_REQUEST["rpwd"]);
		}
		if (!IsSet($_SESSION["right_pwd"]))
		{
			$_SESSION["right_pwd"] = $this->UpDir($this->GetPathTranslated());
		}
		$this->pwd["right"] = $_SESSION["right_pwd"];
		
		if (IsSet($_REQUEST["line_numbers"]))
		{
			$_SESSION["line_numbers"] = $this->StripSlashes($_REQUEST["line_numbers"]);
		}
		if (!IsSet($_SESSION["line_numbers"]))
		{
			$_SESSION["line_numbers"] = "-#";
		}
		
		$this->z = new Zip();
		if (!isset($_SESSION["jumpto_form"])) $_SESSION["jumpto_form"] = "fc.cmd";
	}
	
	var $z;
	
	function UpDir($path)
	{
		return DirName($path);
	}
	
	function StripSlashesArray($array)
	{
		for ($i = 0; $i < Count($array); $i++)
		{
			$result[$i] = $this->StripSlashes($array[$i]);
		}
		return $result;
	}
	
	function StripSlashes($str)
	{
		return get_magic_quotes_gpc() ? StripSlashes($str) : $str;
	}
	
	function Authorize($password)
	{
		if (IsSet($_SESSION["authorized"]))
		{
			return;
		}
		$time = @$_POST["time"];
		if (abs(time() - $time > 60))
		{
			echo $this->GetPageNotAuthorized();
			die();
		}
		if ($password != MD5("db063e2a9866c2487fc3dfbb39996a8e".$time))
		{
			echo $this->GetPageNotAuthorized();
			die();
		}
		$_SESSION["authorized"] = true;
	}
	
	function GetPageNotAuthorized()
	{
		return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/2000/REC-xhtml1-20000126/DTD/xhtml1-strict.dtd">
		<html><head>
		<title>Matmas Commander</title></head><body onload="document.fakeform.plain.focus();">
		<script type="text/javascript">
		<!--
			/*
			* A JavaScript implementation of the RSA Data Security, Inc. MD5 Message
			* Digest Algorithm, as defined in RFC 1321.
			* Version 2.1 Copyright (C) Paul Johnston 1999 - 2002.
			* Other contributors: Greg Holt, Andrew Kepert, Ydnar, Lostinet
			* Distributed under the BSD License
			* See http://pajhome.org.uk/crypt/md5 for more info.
			*/
			
			/*
			* Configurable variables. You may need to tweak these to be compatible with
			* the server-side, but the defaults work in most cases.
			*/
			var hexcase = 0;  /* hex output format. 0 - lowercase; 1 - uppercase        */
			var b64pad  = ""; /* base-64 pad character. "=" for strict RFC compliance   */
			var chrsz   = 8;  /* bits per input character. 8 - ASCII; 16 - Unicode      */
			
			/*
			* These are the functions you\'ll usually want to call
			* They take string arguments and return either hex or base-64 encoded strings
			*/
			function hex_md5(s){ return binl2hex(core_md5(str2binl(s), s.length * chrsz));}
			function b64_md5(s){ return binl2b64(core_md5(str2binl(s), s.length * chrsz));}
			function str_md5(s){ return binl2str(core_md5(str2binl(s), s.length * chrsz));}
			function hex_hmac_md5(key, data) { return binl2hex(core_hmac_md5(key, data)); }
			function b64_hmac_md5(key, data) { return binl2b64(core_hmac_md5(key, data)); }
			function str_hmac_md5(key, data) { return binl2str(core_hmac_md5(key, data)); }
			
			/*
			* Perform a simple self-test to see if the VM is working
			*/
			function md5_vm_test()
			{
			return hex_md5("abc") == "900150983cd24fb0d6963f7d28e17f72";
			}
			
			/*
			* Calculate the MD5 of an array of little-endian words, and a bit length
			*/
			function core_md5(x, len)
			{
			/* append padding */
			x[len >> 5] |= 0x80 << ((len) % 32);
			x[(((len + 64) >>> 9) << 4) + 14] = len;
			
			var a =  1732584193;
			var b = -271733879;
			var c = -1732584194;
			var d =  271733878;
			
			for(var i = 0; i < x.length; i += 16)
			{
				var olda = a;
				var oldb = b;
				var oldc = c;
				var oldd = d;
			
				a = md5_ff(a, b, c, d, x[i+ 0], 7 , -680876936);
				d = md5_ff(d, a, b, c, x[i+ 1], 12, -389564586);
				c = md5_ff(c, d, a, b, x[i+ 2], 17,  606105819);
				b = md5_ff(b, c, d, a, x[i+ 3], 22, -1044525330);
				a = md5_ff(a, b, c, d, x[i+ 4], 7 , -176418897);
				d = md5_ff(d, a, b, c, x[i+ 5], 12,  1200080426);
				c = md5_ff(c, d, a, b, x[i+ 6], 17, -1473231341);
				b = md5_ff(b, c, d, a, x[i+ 7], 22, -45705983);
				a = md5_ff(a, b, c, d, x[i+ 8], 7 ,  1770035416);
				d = md5_ff(d, a, b, c, x[i+ 9], 12, -1958414417);
				c = md5_ff(c, d, a, b, x[i+10], 17, -42063);
				b = md5_ff(b, c, d, a, x[i+11], 22, -1990404162);
				a = md5_ff(a, b, c, d, x[i+12], 7 ,  1804603682);
				d = md5_ff(d, a, b, c, x[i+13], 12, -40341101);
				c = md5_ff(c, d, a, b, x[i+14], 17, -1502002290);
				b = md5_ff(b, c, d, a, x[i+15], 22,  1236535329);
			
				a = md5_gg(a, b, c, d, x[i+ 1], 5 , -165796510);
				d = md5_gg(d, a, b, c, x[i+ 6], 9 , -1069501632);
				c = md5_gg(c, d, a, b, x[i+11], 14,  643717713);
				b = md5_gg(b, c, d, a, x[i+ 0], 20, -373897302);
				a = md5_gg(a, b, c, d, x[i+ 5], 5 , -701558691);
				d = md5_gg(d, a, b, c, x[i+10], 9 ,  38016083);
				c = md5_gg(c, d, a, b, x[i+15], 14, -660478335);
				b = md5_gg(b, c, d, a, x[i+ 4], 20, -405537848);
				a = md5_gg(a, b, c, d, x[i+ 9], 5 ,  568446438);
				d = md5_gg(d, a, b, c, x[i+14], 9 , -1019803690);
				c = md5_gg(c, d, a, b, x[i+ 3], 14, -187363961);
				b = md5_gg(b, c, d, a, x[i+ 8], 20,  1163531501);
				a = md5_gg(a, b, c, d, x[i+13], 5 , -1444681467);
				d = md5_gg(d, a, b, c, x[i+ 2], 9 , -51403784);
				c = md5_gg(c, d, a, b, x[i+ 7], 14,  1735328473);
				b = md5_gg(b, c, d, a, x[i+12], 20, -1926607734);
			
				a = md5_hh(a, b, c, d, x[i+ 5], 4 , -378558);
				d = md5_hh(d, a, b, c, x[i+ 8], 11, -2022574463);
				c = md5_hh(c, d, a, b, x[i+11], 16,  1839030562);
				b = md5_hh(b, c, d, a, x[i+14], 23, -35309556);
				a = md5_hh(a, b, c, d, x[i+ 1], 4 , -1530992060);
				d = md5_hh(d, a, b, c, x[i+ 4], 11,  1272893353);
				c = md5_hh(c, d, a, b, x[i+ 7], 16, -155497632);
				b = md5_hh(b, c, d, a, x[i+10], 23, -1094730640);
				a = md5_hh(a, b, c, d, x[i+13], 4 ,  681279174);
				d = md5_hh(d, a, b, c, x[i+ 0], 11, -358537222);
				c = md5_hh(c, d, a, b, x[i+ 3], 16, -722521979);
				b = md5_hh(b, c, d, a, x[i+ 6], 23,  76029189);
				a = md5_hh(a, b, c, d, x[i+ 9], 4 , -640364487);
				d = md5_hh(d, a, b, c, x[i+12], 11, -421815835);
				c = md5_hh(c, d, a, b, x[i+15], 16,  530742520);
				b = md5_hh(b, c, d, a, x[i+ 2], 23, -995338651);
			
				a = md5_ii(a, b, c, d, x[i+ 0], 6 , -198630844);
				d = md5_ii(d, a, b, c, x[i+ 7], 10,  1126891415);
				c = md5_ii(c, d, a, b, x[i+14], 15, -1416354905);
				b = md5_ii(b, c, d, a, x[i+ 5], 21, -57434055);
				a = md5_ii(a, b, c, d, x[i+12], 6 ,  1700485571);
				d = md5_ii(d, a, b, c, x[i+ 3], 10, -1894986606);
				c = md5_ii(c, d, a, b, x[i+10], 15, -1051523);
				b = md5_ii(b, c, d, a, x[i+ 1], 21, -2054922799);
				a = md5_ii(a, b, c, d, x[i+ 8], 6 ,  1873313359);
				d = md5_ii(d, a, b, c, x[i+15], 10, -30611744);
				c = md5_ii(c, d, a, b, x[i+ 6], 15, -1560198380);
				b = md5_ii(b, c, d, a, x[i+13], 21,  1309151649);
				a = md5_ii(a, b, c, d, x[i+ 4], 6 , -145523070);
				d = md5_ii(d, a, b, c, x[i+11], 10, -1120210379);
				c = md5_ii(c, d, a, b, x[i+ 2], 15,  718787259);
				b = md5_ii(b, c, d, a, x[i+ 9], 21, -343485551);
			
				a = safe_add(a, olda);
				b = safe_add(b, oldb);
				c = safe_add(c, oldc);
				d = safe_add(d, oldd);
			}
			return Array(a, b, c, d);
			
			}
			
			/*
			* These functions implement the four basic operations the algorithm uses.
			*/
			function md5_cmn(q, a, b, x, s, t)
			{
			return safe_add(bit_rol(safe_add(safe_add(a, q), safe_add(x, t)), s),b);
			}
			function md5_ff(a, b, c, d, x, s, t)
			{
			return md5_cmn((b & c) | ((~b) & d), a, b, x, s, t);
			}
			function md5_gg(a, b, c, d, x, s, t)
			{
			return md5_cmn((b & d) | (c & (~d)), a, b, x, s, t);
			}
			function md5_hh(a, b, c, d, x, s, t)
			{
			return md5_cmn(b ^ c ^ d, a, b, x, s, t);
			}
			function md5_ii(a, b, c, d, x, s, t)
			{
			return md5_cmn(c ^ (b | (~d)), a, b, x, s, t);
			}
			
			/*
			* Calculate the HMAC-MD5, of a key and some data
			*/
			function core_hmac_md5(key, data)
			{
			var bkey = str2binl(key);
			if(bkey.length > 16) bkey = core_md5(bkey, key.length * chrsz);
			
			var ipad = Array(16), opad = Array(16);
			for(var i = 0; i < 16; i++)
			{
				ipad[i] = bkey[i] ^ 0x36363636;
				opad[i] = bkey[i] ^ 0x5C5C5C5C;
			}
			
			var hash = core_md5(ipad.concat(str2binl(data)), 512 + data.length * chrsz);
			return core_md5(opad.concat(hash), 512 + 128);
			}
			
			/*
			* Add integers, wrapping at 2^32. This uses 16-bit operations internally
			* to work around bugs in some JS interpreters.
			*/
			function safe_add(x, y)
			{
			var lsw = (x & 0xFFFF) + (y & 0xFFFF);
			var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
			return (msw << 16) | (lsw & 0xFFFF);
			}
			
			/*
			* Bitwise rotate a 32-bit number to the left.
			*/
			function bit_rol(num, cnt)
			{
			return (num << cnt) | (num >>> (32 - cnt));
			}
			
			/*
			* Convert a string to an array of little-endian words
			* If chrsz is ASCII, characters >255 have their hi-byte silently ignored.
			*/
			function str2binl(str)
			{
			var bin = Array();
			var mask = (1 << chrsz) - 1;
			for(var i = 0; i < str.length * chrsz; i += chrsz)
				bin[i>>5] |= (str.charCodeAt(i / chrsz) & mask) << (i%32);
			return bin;
			}
			
			/*
			* Convert an array of little-endian words to a string
			*/
			function binl2str(bin)
			{
			var str = "";
			var mask = (1 << chrsz) - 1;
			for(var i = 0; i < bin.length * 32; i += chrsz)
				str += String.fromCharCode((bin[i>>5] >>> (i % 32)) & mask);
			return str;
			}
			
			/*
			* Convert an array of little-endian words to a hex string.
			*/
			function binl2hex(binarray)
			{
			var hex_tab = hexcase ? "0123456789ABCDEF" : "0123456789abcdef";
			var str = "";
			for(var i = 0; i < binarray.length * 4; i++)
			{
				str += hex_tab.charAt((binarray[i>>2] >> ((i%4)*8+4)) & 0xF) +
					hex_tab.charAt((binarray[i>>2] >> ((i%4)*8  )) & 0xF);
			}
			return str;
			}
			
			/*
			* Convert an array of little-endian words to a base-64 string
			*/
			function binl2b64(binarray)
			{
			var tab = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
			var str = "";
			for(var i = 0; i < binarray.length * 4; i += 3)
			{
				var triplet = (((binarray[i   >> 2] >> 8 * ( i   %4)) & 0xFF) << 16)
							| (((binarray[i+1 >> 2] >> 8 * ((i+1)%4)) & 0xFF) << 8 )
							|  ((binarray[i+2 >> 2] >> 8 * ((i+2)%4)) & 0xFF);
				for(var j = 0; j < 4; j++)
				{
				if(i * 8 + j * 6 > binarray.length * 32) str += b64pad;
				else str += tab.charAt((triplet >> 6*(3-j)) & 0x3F);
				}
			}
			return str;
			}

			function login()
			{
				var time = timeoffset + Math.round((new Date).getTime()/1000);
				document.realform.time.value = time;
				document.realform.heslo.value = hex_md5(hex_md5(document.fakeform.plain.value) + time);
				document.realform.submit();
				return false;
			}	
		//-->
		</script>
		<h4>Matmas Commander: version '.$this->version.'</h4>
		<h1>Authorization failed</h1>
		<form action="" name="fakeform" onsubmit="return login();">
			<input type="password" name="plain" />
			<input type="submit" value="OK" />
			<input type="hidden" name="servertime" value="'.time().'" />
		</form>
		<form action="'.$_SERVER["PHP_SELF"].'" method="post" name="realform">
			<input type="hidden" name="heslo" />
			<input type="hidden" name="time" />
		</form>
		<script type="text/javascript">
		<!--
			var timeoffset = document.fakeform.servertime.value - Math.round((new Date).getTime()/1000);
		//-->
		</script>
		</body></html>';
	}
	
	var $errorMessages = "";
	
	function AddErrorMessage($text)
	{
		$this->errorMessages .= $text."<br />\n";
	}
	
	function PrintErrorMessages()
	{
		if ($this->errorMessages != "")
		{
			echo "<div>Error: ".$this->errorMessages."</div>";
		}
	}
	
	var $bottomPart = "";
	
	function ShowAtBottom($text)
	{
		$this->bottomPart .= $text;
	}
	
	function ShowBottomPart()
	{
		if ($this->bottomPart != "")
		{
			echo "<div>".$this->bottomPart."</div>";
		}
	}
	
	var $headTags = "";
	
	function AddHeadTag($text)
	{
		$this->headTags .= $text;
	}
	
	function WriteHeadTags()
	{
		echo $this->headTags;
	}
	
	var $bottomExecution = "";
	
	function ExecuteAtBottom($command)
	{
		$this->bottomExecution .= $command;
	}
	
	function ExecuteBottom()
	{
		eval($this->bottomExecution);
	}
	
	var $leftPanelItems, $rightPanelItems;
	
	function nItems($leftRight)
	{
		if ($leftRight == "left")
		{
			return Count($this->leftPanelItems);
		}
		if ($leftRight == "right")
		{
			return Count($this->rightPanelItems);
		}
	}
	
	function IsSomethingSelected()
	{
		if ($this->nItems("left") + $this->nItems("right") > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function IsSomethingSelectedLR($leftRight)
	{
		if ($this->nItems($leftRight) > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function IsSelectedOneFile($addErrorMessage = false, $fileDir = "file", $leftRight = "both")
	{
		if ($leftRight == "both")
		{
			if ($this->nItems("left") == 1 && $this->nItems("right") == 0 && ($fileDir == "both" || $this->z->FileType($this->leftPanelItems[0]) == $fileDir))
			{
				return true;
			}
			if ($this->nItems("left") == 0 && $this->nItems("right") == 1 && ($fileDir == "both" || $this->z->FileType($this->rightPanelItems[0]) == $fileDir))
			{
				return true;
			}
		}
		if ($leftRight == "left" && $this->nItems("left") == 1 && ($fileDir == "both" || $this->z->FileType($this->leftPanelItems[0]) == $fileDir))
		{
			return true;
		}
		if ($leftRight == "right" && $this->nItems("right") == 1 && ($fileDir == "both" || $this->z->FileType($this->rightPanelItems[0]) == $fileDir))
		{
			return true;
		}
		if ($addErrorMessage)
		{
			$this->AddErrorMessage("Select one file only");
		}
		return false;
	}
	
	function GetSelectedFileName($fileDir = "file", $leftRight = "both")
	{
		if ($this->IsSelectedOneFile(false, $fileDir, $leftRight))
		{
			if (($leftRight == "left" || $leftRight == "both") && $this->nItems("left") == 1)
			{
				return $this->leftPanelItems[0];
			}
			if (($leftRight == "right" || $leftRight == "both") && $this->nItems("right") == 1)
			{
				return $this->rightPanelItems[0];
			}
		}
	}

	function GetPathTranslated()
	{
		if (@$_SERVER["PATH_TRANSLATED"] != "")
		{
			return StrTr($this->StripSlashes($_SERVER["PATH_TRANSLATED"]), "\\", "/");
		}
		else if ($_SERVER["SCRIPT_FILENAME"] != "")
		{
			return StrTr($this->StripSlashes($_SERVER["SCRIPT_FILENAME"]), "\\", "/");
		}
		else if ($_SERVER["DOCUMENT_ROOT"] != "")
		{
			return StrTr($this->StripSlashes($_SERVER["DOCUMENT_ROOT"]), "\\", "/");
		}
		else
		{
			return "/";
		}
	}
	
	function GetServerRoot($addErrorMessage = false)
	{
		$pathTranslated = $this->GetPathTranslated();
		$charactersToKeep = StrLen($pathTranslated) - StrLen($_SERVER["PHP_SELF"]);
		if (SubStr($pathTranslated, $charactersToKeep, StrLen($pathTranslated)) == $_SERVER["PHP_SELF"])
		{
			return SubStr($pathTranslated, 0, $charactersToKeep);
		}
		if ($addErrorMessage)
		{
			$this->AddErrorMessage("Unable to redirect (unable to determine www root), try to download it manually");
		}
		return false;
	}
	
	function GetServerLocation($fileName, $addErrorMessage = false)
	{
		if (SubStr($fileName, 0, StrLen($this->GetServerRoot())) == $this->GetServerRoot())
		{
			return SubStr($fileName, StrLen($this->GetServerRoot()), StrLen($fileName));
		}
		if ($addErrorMessage)
		{
			$this->AddErrorMessage("Unable to redirect (file out of www server range), try to download it manually");
		}
		return false;
	}
	
	function IsFileReachableByServer($fileName)
	{
		return ($this->GetServerRoot() && $this->GetServerLocation($fileName) && !IsZipFileInPath($fileName));
	}
	
	var $pwd;
	
	function OpenViewer($fn, $open = "not_whole")
	{
		$output = "";
		if ($this->GetServerLocation($fn))
		{
			$output .= "<a href=\"".$this->GetServerLocation($fn)."\">$fn</a><br />\n";
		}
		else
		{
			$output .= "<b>$fn</b><br />\n";
		}
		$output .= "<b>".Number_Format($this->z->FileSize($fn))." bytes</b><br />\n";
		$not_whole_bytes = 1000;
		if ($open == "whole" && $this->z->FileSize($fn) > 500000)
		{
			$open = "not_whole";
			$not_whole_bytes = 500000;
		}
		if ($open == "whole" && GetFileExtension($fn) == ".php")
		{
			$this->ExecuteAtBottom("\$z = new Zip(); \$z->HighLight_File(\"$fn\");");
		}
		else
		{
			$fp = $this->z->FOpen($fn, "r");
			if (!$fp)
			{
				$this->AddErrorMessage("not possible to open file");
				return false;
			}
			if ($open == "not_whole")
			{
				$line = $this->z->FRead($fp, $not_whole_bytes);
				$output .= "<pre>".HTMLSpecialChars($line)."</pre>";
				if ( !$this->z->FEof($fp) )
				{
					$output .= "<b>continues...</b>";
				}
			}
			else
			{
				$output .= "<pre>";
				while (!$this->z->FEof($fp))
				{
					$line = $this->z->FGetS($fp, 65535);
					$output .= HTMLSpecialChars($line);
				}
				$output .= "</pre>";
			}
			$this->z->FClose($fp);
		}
		$this->ShowAtBottom($output);
	}

	function OpenEditor($fn)
	{
		$file_size = $this->z->FileSize($fn);
		if ($file_size > 500000)
		{
			$this->AddErrorMessage("File $fn is too big for editing (".Number_Format($file_size)." bytes)");
			return false;
		}
		$output = "";
		if ($this->GetServerLocation($fn))
		{
			$output .= "<a href=\"".$this->GetServerLocation($fn)."\">$fn</a><br />\n";
		}
		else
		{
			$output .= "<b>$fn</b><br />\n";
		}
		$fp = $this->z->FOpen($fn, "r");
		if (!$fp)
		{
			$this->AddErrorMessage("not possible to open file");
			return false;
		}
		$output .= '<a name="editor" />';
		$output .= '<form action="'.$_SERVER["PHP_SELF"].'#editor" method="post">';
		$output .= '<input type="submit" name="line_numbers" value="'.($_SESSION["line_numbers"] == "-#" ? "+#" : "-#").'" />';
		$output .= '<input type="submit" name="save" value="Save" accesskey="s" title="alt+s" /> as ';
		$output .= '<input type="text" name="filenametosave" value="'.$fn.'" size="50" /><br />';
		$output .= '<textarea name="editing[]" cols="70" rows="40" wrap="off" onkeydown="return insertTab(event,this);" onkeyup="return insertTab(event,this);" onkeypress="return insertTab(event,this);">';
		$this->AddHeadTag('
		<script type="text/javascript"><!--
/**
* Insert a tab at the current text position in a textarea
* Jan Dittmer, jdittmer@ppp0.net, 2005-05-28
* Inspired by http://www.forum4designers.com/archive22-2004-9-127735.html
* Tested on: 
*   Mozilla Firefox 1.0.3 (Linux)
*   Mozilla 1.7.8 (Linux)
*   Epiphany 1.4.8 (Linux)
*   Internet Explorer 6.0 (Linux)
* Does not work in: 
*   Konqueror (no tab inserted, but focus stays)
*/
function insertTab(event,obj) {
	var tabKeyCode = 9;
	if (event.which) // mozilla
		var keycode = event.which;
	else // ie
		var keycode = event.keyCode;
	if (keycode == tabKeyCode) {
		if (event.type == "keydown") {
			if (obj.setSelectionRange) {
				// mozilla
				var s = obj.selectionStart;
				var e = obj.selectionEnd;
				obj.value = obj.value.substring(0, s) + 
					"\t" + obj.value.substr(e);
				obj.setSelectionRange(s + 1, s + 1);
				obj.focus();
			} else if (obj.createTextRange) {
				// ie
				document.selection.createRange().text="\t"
				obj.onblur = function() { this.focus(); this.onblur = null; };
			} else {
				// unsupported browsers
			}
		}
		if (event.returnValue) // ie ?
			event.returnValue = false;
		if (event.preventDefault) // dom
			event.preventDefault();
		return false; // should work in all browsers
	}
	return true;
}
//-->
</script>');
		$cislo_riadku = 1;
		while (!$this->z->FEof($fp))
		{
			if ($_SESSION["line_numbers"] == "+#")
			{
				$navesie = "~";
				for ($w = 0; $w < 6-StrLen($cislo_riadku); $w++)
				{
					$navesie .= " ";
				}
				$navesie .= ($cislo_riadku++).":";
				$output .= $navesie;
			}
			$output .= HTMLSpecialChars($this->z->FGetS($fp, 65535));
		}
		$output .= '</textarea>';
		$output .= '</form>';
		$this->z->FClose($fp);
		$this->ShowAtBottom($output);
	}
	
	function DrawPanel($leftRight)
	{
		$lr = ($leftRight == "left") ? "l" : "r";
		$path = $this->pwd[$leftRight];
		echo '<input type="text" name="'.$lr.'pwd" value="'.$path.'" size="55" class="pwd" /><br />'."\n";
		echo '<select name="'.$lr.'[]" multiple size="22" ondblclick="document.f.submit();" onkeydown="return Tab(event,this,\''.$lr.'\');" onkeyup="return Tab(event,this,\''.$lr.'\');" onkeypress="return Tab(event,this,\''.$lr.'\');">'."\n";
		$d = $this->z->Dir($path);
		if ($d->Valid())
		{
			if ($this->UpDir($path) != "")
			{
				echo '<option value="'.$this->UpDir($path).'">..</option>'."\n";
			}
			while ($fn = $d->Read())
			{
				if ($fn == '.' || $fn == '..')
				{
					continue;
				}
				$ft = $d->LastFileType();
				if ($ft == 'dir')
				{
					$dns[] = $fn;
				}
				if ($ft == 'file')
				{
					$fns[] = $fn;
				}
			}
			@Sort($dns); //if any
			@Sort($fns);
			for ($i = 0; $i < Count($dns); $i++)
			{
				echo '<option value="'.$path.'/'.$dns[$i].'">+'.$dns[$i].'</option>'."\n";
			}
			for ($i = 0; $i < Count($fns); $i++)
			{
				if (IsZipFile($path.'/'.$fns[$i]) && $this->z->ZipEnabled())
				{
					echo '<option value="'.$path.'/'.$fns[$i].'">*'.$fns[$i].'</option>'."\n";
				}
				else
				{
					echo '<option value="'.$path.'/'.$fns[$i].'">'.$fns[$i].'</option>'."\n";
				}
			}
			$d->Close();
		}
		else
		{
			echo '<option>path does not exist</option>'."\n";
		}
		echo '</select>'."\n";
	}

	function statModeToText($statmode)
	{
		$mode = "";
		if ($statmode & 0000400)
			$mode .= "r";
		else
			$mode .= "-";
		if ($statmode & 0000200)
			$mode .= "w";
		else
			$mode .= "-";
		if ($statmode & 0000100)
			$mode .= "x";
		else
			$mode .= "-";
		if ($statmode & 0000040)
			$mode .= "r";
		else
			$mode .= "-";
		if ($statmode & 0000020)
			$mode .= "w";
		else
			$mode .= "-";
		if ($statmode & 0000010)
			$mode .= "x";
		else
			$mode .= "-";
		if ($statmode & 0000004)
			$mode .= "r";
		else
			$mode .= "-";
		if ($statmode & 0000002)
			$mode .= "w";
		else
			$mode .= "-";
		if ($statmode & 0000001)
			$mode .= "x";
		else
			$mode .= "-";
		return $mode;
	}
	
	function Action($actionType)
	{
		switch ($actionType)
		{
			case "exit":
			{
				session_destroy();
				echo '<html><head><title>Logout</title>
				<META HTTP-EQUIV="Refresh" CONTENT="0; URL='.$_SERVER["PHP_SELF"].'">
				</head><body></body></html>';
				Flush();
				Die();
			}
			case "download":
			{
				if ( !($this->IsSelectedOneFile(true)))
				{
					return;
				}
				$fn = $this->GetSelectedFileName();
				$ext = GetFileExtension($fn);
				
				if ($this->IsFileReachableByServer($fn) && $ext != ".php" && $ext != ".php3")
				{
					$target = $this->GetServerLocation($fn);
				}
				else
				{
					@$this->z->MkDir("temp", 0777); // if not exists
					$newfn = BaseName($fn).($ext == ".php" || $ext == ".php3" ? "_" : "");
					$this->z->Copy($fn, "./temp/".$newfn);
					$target = "./temp/".$newfn;
				}
				$this->AddHeadTag('<META HTTP-EQUIV="Refresh" CONTENT="0; URL='.$target.'">');
			}
			case "execute":
			{
				Exec($this->StripSlashes($_REQUEST["cmd"]), $output_array);
				$output = "";
				for($i = 0; $i < Count($output_array); $i++)
				{
					$output .= HTMLSpecialChars($output_array[$i])."\n";
				}
				$this->ShowAtBottom("<PRE>".$output."</PRE>");
				$_SESSION["jumpto_form"] = "fc.cmd";
				break;
			}
			case "php":
			{
				$output = HTMLSpecialChars(eval($this->StripSlashes($_REQUEST["php"]).";"));
				$this->ShowAtBottom("<PRE>".$output."</PRE>");
				$_SESSION["jumpto_form"] = "php.php";
				break;
			}
			case "upload":
			{
				if ($this->IsSomethingSelected())
				{
					if ($this->nItems("left") > $this->nItems("right"))
					{
						$target = $this->pwd["left"]."/".$_FILES["subor"]["name"];
						$result = $this->z->Copy($_FILES["subor"]["tmp_name"], $target);
					}
					if ($this->nItems("left") < $this->nItems("right"))
					{
						$target = $this->pwd["right"]."/".$_FILES["subor"]["name"];
						$result = $this->z->Copy($_FILES["subor"]["tmp_name"], $target);
					}
					if ($this->nItems("left") == $this->nItems("right"))
					{
						$this->AddErrorMessage("Select destination panel properly");
						break;
					}
				}
				else
				{
					@$this->z->MkDir("upload", 0777); //if not exists
					$target = "./upload/".$_FILES["subor"]["name"];
					$result = $this->z->Copy($_FILES["subor"]["tmp_name"], $target);
				}
				if ($result)
				{
						$output = $_FILES["subor"]["name"]." ".Number_Format($_FILES["subor"]["size"])." bytes uploaded to ";
						if ($this->GetServerLocation($target))
						{
							$output .= "<a href=\"".$this->GetServerLocation($target)."\">$target</a>";
						}
						else
						{
							$output .= $target;
						}
						$this->ShowAtBottom($output);
				}
				else
				{
					$this->AddErrorMessage("Error while uploading file! (or empty file was uploaded)");
				}
				break;
			}
			case "delete":
			{
				for($i = 0; $i < $this->nItems("left"); $i++)
				{
					if ($this->leftPanelItems[$i] != $this->UpDir($this->pwd["left"])) //deleting of virtual ".." directory not permitted
					{
						if ( !$this->z->UnlinkR($this->leftPanelItems[$i]))
						{
							$this->AddErrorMessage("not possible to delete ".$this->leftPanelItems[$i]);
						}
					}
				}
				for($i = 0; $i < $this->nItems("right"); $i++)
				{
					if ($this->rightPanelItems[$i] != $this->UpDir($this->pwd["right"]))
					{
						if ( !$this->z->UnlinkR($this->rightPanelItems[$i]))
						{
							$this->AddErrorMessage("not possible to delete ".$this->rightPanelItems[$i]);
						}
					}
				}
				break;
			}
			case "make_directory":
			{
				if ($this->IsSomethingSelected())
				{
					if ($this->nItems("left") > $this->nItems("right"))
					{
						if ( !$this->z->MkDir($this->pwd["left"]."/".$_REQUEST["co"], 0777) )
						{
							$this->AddErrorMessage("not possible to make new subdirectory");
						}
					}
					if ($this->nItems("left") < $this->nItems("right"))
					{
						if ( !$this->z->MkDir($this->pwd["right"]."/".$_REQUEST["co"], 0777) )
						{
							$this->AddErrorMessage("not possible to make new subdirectory");
						}
					}
				}
				break;
			}
			case "make_file":
			{
				if ($this->IsSomethingSelected())
				{
					if ($this->nItems("left") > $this->nItems("right"))
					{
						if ( !$this->z->Touch($this->pwd["left"]."/".$_REQUEST["co"]) )
						{
							$this->AddErrorMessage("not possible to make new file");
						}
					}
					if ($this->nItems("left") < $this->nItems("right"))
					{
						if ( !$this->z->Touch($this->pwd["right"]."/".$_REQUEST["co"]) )
						{
							$this->AddErrorMessage("not possible to make new file");
						}
					}
				}
				break;
			}
			case "copy":
			{
				for($i = 0; $i < $this->nItems("left"); $i++)
				{
					if (!$this->z->CopyR($this->leftPanelItems[$i], $this->pwd["right"]."/".BaseName($this->leftPanelItems[$i])))
					{
						$this->AddErrorMessage("not possible to copy ".$this->leftPanelItems[$i]);
					}
				}
				for($i = 0; $i < $this->nItems("right"); $i++)
				{
					if (!$this->z->CopyR($this->rightPanelItems[$i], $this->pwd["left"]."/".BaseName($this->rightPanelItems[$i])))
					{
						$this->AddErrorMessage("not possible to copy ".$this->rightPanelItems[$i]);
					}
				}
				break;
			}
			case "move":
			{
				for($i = 0; $i < $this->nItems("left"); $i++)
				{
					if ( !$this->z->Rename($this->leftPanelItems[$i], $this->pwd["right"]."/".BaseName($this->leftPanelItems[$i])))
					{
						$this->AddErrorMessage("not possible to move ".$this->leftPanelItems[$i]);
					}
				}
				for($i = 0; $i < $this->nItems("right"); $i++)
				{
					if ( !$this->z->Rename($this->rightPanelItems[$i], $this->pwd["left"]."/".BaseName($this->rightPanelItems[$i])))
					{
						$this->AddErrorMessage("not possible to move ".$this->rightPanelItems[$i]);
					}
				}
				break;
			}
			case "rename":
			{
				if ($this->IsSelectedOneFile(false, "both"))
				{
					if ($this->IsSelectedOneFile(false, "both", "left"))
					{
						if ( !$this->z->Rename($this->leftPanelItems[0], $this->pwd["left"]."/".$_REQUEST["co"]))
						{
							$this->AddErrorMessage("not possible to rename");
						}					
					}
					if ($this->IsSelectedOneFile(false, "both", "right"))
					{
						if ( !$this->z->Rename($this->rightPanelItems[0], $this->pwd["right"]."/".$_REQUEST["co"]))
						{
							$this->AddErrorMessage("not possible to rename");
						}
					}
				}
				break;
			}
			case "edit":
			{
				if (IsSet($_REQUEST["save"]) || IsSet($_REQUEST["line_numbers"]))
				{
					$this->OpenEditor($this->StripSlashes($_REQUEST["filenametosave"]));
				}
				else
				{
					if ( !($this->IsSelectedOneFile(true)))
					{
						return;
					}
					$this->OpenEditor($this->GetSelectedFileName());
				}
				break;
			}
			case "save":
			{
				$fn = $this->StripSlashes($_REQUEST["filenametosave"]);
				$text = $_REQUEST["editing"];
				$x = "^~( )*[0-9]+:";
				$fp = $this->z->FOpen($fn, "w");
				if (!$fp)
				{
					$this->AddErrorMessage("not possible to save file");
					return;
				}
				$this->ShowAtBottom("File saved.<br />");
				
				$riadky = Explode("\n", $text[0]);
				for($q = 0; $q < Count($riadky); $q++)
				{
					if (EReg($x, $riadky[$q]))
					{
						$this->z->FWrite($fp, EReg_Replace($x."([^$]*)", "\\2", $this->StripSlashes($riadky[$q])));
					}
					else
					{
						$this->z->FWrite($fp, $this->StripSlashes($riadky[$q]));
					}
					if ($q+1 != Count($riadky))
					{
						$this->z->FWrite($fp, "\n");
					}
				}
				$this->z->FClose($fp);
				break;
			}
			case "info":
			{
				if ($this->IsSomethingSelected())
				{
					if ($this->IsSelectedOneFile())
					{
						$this->OpenViewer($this->GetSelectedFileName());
					}
					else
					{
						$output = "<tr><th>name</th><th>bytes</th><th>uid</th><th>gid</th><th>mode</th></tr>\n";
						$total_bytes = 0;
						for($i = 0; $i < Count($this->leftPanelItems); $i++)
						{
							$bytes = $this->z->FileSize($this->leftPanelItems[$i]);
							$total_bytes += $bytes;
							$output .= "<tr>";
							$output .= "<td>".$this->leftPanelItems[$i].'</td><td class="numeric">'.Number_Format($bytes)."</td>";
							$stat = stat($this->leftPanelItems[$i]);
							$mode = $this->statModeToText($stat["mode"]);
							$output .= "<td>".$stat["uid"]."</td><td>".$stat["gid"]."</td><td><tt>".$mode."</tt></td>";
							$output .= "<tr>\n";
						}
						for($i = 0; $i < Count($this->rightPanelItems); $i++)
						{
							$bytes = $this->z->FileSize($this->rightPanelItems[$i]);
							$total_bytes += $bytes;
							$output .= "<tr>";
							$output .= "<td>".$this->rightPanelItems[$i].'</td><td class="numeric">'.Number_Format($bytes)."</td>";
							$stat = stat($this->rightPanelItems[$i]);
							$mode = $this->statModeToText($stat["mode"]);
							$output .= "<td>".$stat["uid"]."</td><td>".$stat["gid"]."</td><td><tt>".$mode."</tt></td>";
							$output .= "<tr>\n";
						}
						$output .= '<tr><td><b>total</b></td><td class="numeric"><b>'.Number_Format($total_bytes)."</b></td><td><b>bytes</b></td></tr>\n";
						$this->ShowAtBottom('<table class="overview">'.$output."</table>");
					}
				}
				break;
			}
			case "view":
			{
				if ($this->IsSelectedOneFile(false, "dir", "left"))
				{
					$this->pwd["left"] = $this->GetSelectedFileName("dir", "left");
				}
				if ($this->IsSelectedOneFile(false, "dir", "right"))
				{
					$this->pwd["right"] = $this->GetSelectedFileName("dir", "right");
				}
				if ($this->IsSelectedOneFile() && !IsZipFile($this->GetSelectedFileName()))
				{
					$this->OpenViewer($this->GetSelectedFileName(), "whole");
				}
				break;
			}
			case "chmod0777":
			{
				for($i = 0; $i < Count($this->leftPanelItems); $i++)
				{
					chmod($this->leftPanelItems[$i], 0777);
				}
				for($i = 0; $i < Count($this->rightPanelItems); $i++)
				{
					chmod($this->rightPanelItems[$i], 0777);
				}
			}
			case "chmod1777":
			{
				for($i = 0; $i < Count($this->leftPanelItems); $i++)
				{
					chmod($this->leftPanelItems[$i], 1777);
				}
				for($i = 0; $i < Count($this->rightPanelItems); $i++)
				{
					chmod($this->rightPanelItems[$i], 1777);
				}
			}
			case "chmod0755":
			{
				for($i = 0; $i < Count($this->leftPanelItems); $i++)
				{
					chmod($this->leftPanelItems[$i], 0755);
				}
				for($i = 0; $i < Count($this->rightPanelItems); $i++)
				{
					chmod($this->rightPanelItems[$i], 0755);
				}
				echo "0644";
			}
			case "chmod0644":
			{
				for($i = 0; $i < Count($this->leftPanelItems); $i++)
				{
					chmod($this->leftPanelItems[$i], 0644);
				}
				for($i = 0; $i < Count($this->rightPanelItems); $i++)
				{
					chmod($this->rightPanelItems[$i], 0644);
				}
				
			}
			case "untar":
			{
				if ($this->IsSelectedOneFile())
				{
					$archive = $this->GetSelectedFileName();
/*					$tar = new tar_file($archive);
					$tar->extract_files();
					foreach ($tar->files as $file)
					{
						$output .= "File " + $file['name'] + " is " + $file['stat'][7] + " bytes\n";
					}*/
					
					$untar = new untar($archive);
					$a = $untar->getfilelist();
					$output = "";
					for ($i=0; $i<count($a); $i++)
					{
						$filename = $a[$i]["filename"];
						$filetype = $a[$i]["filetype"];
						$filesize = $a[$i]["filesize"];
						$offset   = $a[$i]["offset"];
						if (file_exists($filename) && (is_dir($filename) || is_file($filename) && filesize($filename) == $filesize))
						{
							$output .= "skipping $filetype $filename\n";
						}
						else if ($filetype == "directory")
						{
							$output .= "inflating  $filename\n";
							$dirname = substr($filename, 0, strlen($filename)-1);
							mkdir($dirname, 0775);
							chmod($dirname, 0775);
						}
						else if ($filetype == "file")
						{
							$output .= "extracting $filename\n";
							if ($filesize == 0)
							{
								$f = fopen($filename, "w");
								fclose($f);
							}
							else
							{
								$tarfile = fopen($archive,'r');
								if ($tarfile)
								{
									fseek($tarfile, $offset);
									$data = fread($tarfile, $filesize);
									fclose($tarfile);
									$f = fopen($filename, "w");
									fwrite($f, $data, $filesize);
									fclose($f);
								}
							}
							chmod($filename, 0644);
						}
						else
						{
							$output .= "$filetype $filename\n";
						}
					}
					
					$this->ShowAtBottom("<pre>$output</pre>");
				}
			}
		}
	}
}

class Zip
{
	function CopyR($source, $dest)
	{
		// Simple copy for a file
		if ($this->is_file($source)) {
			return $this->copy($source, $dest);
		}

		// Make destination directory
		if (!$this->is_dir($dest)) {
			$this->mkdir($dest, 0777);
		}

		// Loop through the folder
		$dir = $this->dir($source);
		while (false !== $entry = $dir->read()) {
			// Skip pointers
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			// Deep copy directories
			if ($this->is_dir("$source/$entry") && ($dest !== "$source/$entry")) {
				$this->copyr("$source/$entry", "$dest/$entry");
			} else {
				$this->copy("$source/$entry", "$dest/$entry");
			}
		}

		// Clean up
		$dir->close();
		return true;
	}

	function UnlinkR($dirname)
	{
		// Simple delete for a file
		if ($this->is_file($dirname) || IsZipFile($dirname)) {
			return $this->unlink($dirname);
		}
		// Loop through the folder
		$dir = $this->dir($dirname);
		while (false !== $entry = $dir->read()) {
			// Skip pointers
			if ($entry == '.' || $entry == '..') {
				continue;
			}
			
			// Deep delete directories
			if ($this->is_dir("$dirname/$entry")) {
				$this->unlinkr("$dirname/$entry");
			} else {
				$this->unlink("$dirname/$entry");
			}
		}
		// Clean up
		$dir->close();
		@$dir->close(); // BUG in PHP 4.1.0 with Windoze 2000
		return $this->rmdir($dirname);
	}
	
	function FileSizeR($dirname)
	{
		if ($this->is_file($dirname))
		{
			return $this->FileSize($dirname);
		}
		if (IsZipFile($dirname))
		{
			return FileSize($dirname);
		}
		// Loop through the folder
		$dir = $this->dir($dirname);
		$sum = 0;
		while (false !== $entry = $dir->read()) {
			// Skip pointers
			if ($entry == '.' || $entry == '..') {
				continue;
			}
			
			if ($this->is_dir("$dirname/$entry")) {
				$sum += $this->FileSizeR("$dirname/$entry");
			} else {
				$sum += @$this->FileSize("$dirname/$entry"); //if not corrupt files
			}
		}
		// Clean up
		$dir->close();
		return $sum;
	}
	
	function ZipEnabled()
	{
		if (function_exists('gzopen') && Is_Readable(PCLZIPLIB))
		{
			require_once(PCLZIPLIB);
			return true;
		}
		return false;
	}
	
	function IsInZip($path)
	{
		return IsZipFileInPath($path) && !IsZipFile($path) && $this->ZipEnabled();
	}
	
	function HighLight_File($path)
	{
		if ( !$this->IsInZip($path))
		{
			return HighLight_File($path);
		}
		$temp_dir = UniqID("tmp");
		MkDir($temp_dir, 0777);
		$temp_filename = $temp_dir."/".BaseName($path);
		if ( !$this->Copy($path, $temp_filename))
		{
			return false;
		}
		$result = HighLight_File($temp_filename);
		UnLink($temp_filename);
		RmDir($temp_dir);
		return $result;
	}
	
	function FOpen($path, $mode)
	{
		if ( !$this->IsInZip($path))
		{
			return FOpen($path, $mode);
		}
		$temp_dir = UniqID("tmp");
		MkDir($temp_dir, 0777);
		$temp_filename = $temp_dir."/".BaseName($path);
		if ( !$this->Copy($path, $temp_filename))
		{
			return false;
		}
		$result["fp"] = FOpen($temp_filename, $mode);
		$result["fn"] = $temp_filename;
		$result["temp_dir"] = $temp_dir;
		$result["origin"] = $path;
		$result["mode"] = $mode;
		return $result;
	}

	function FClose($fp)
	{
		if ( !IsSet($fp["fn"]))
		{
			return FClose($fp);
		}
		$result = FClose($fp["fp"]);
		if ($fp["mode"] != "r")
		{
			$result = $result && $this->Copy($fp["fn"], $fp["origin"]);
		}
		UnLink($fp["fn"]);
		RmDir($fp["temp_dir"]);
		return $result;
	}

	function FWrite($fp, $string)
	{
		if ( !IsSet($fp["fn"]))
		{
			return FWrite($fp, $string);
		}
		return FWrite($fp["fp"], $string);
	}

	function FRead($fp, $length)
	{
		if ( !IsSet($fp["fn"]))
		{
			return FRead($fp, $length);
		}
		return FRead($fp["fp"], $length);
	}

	function FGetS($fp, $length)
	{
		if ( !IsSet($fp["fn"]))
		{
			return FGetS($fp, $length);
		}
		return FGetS($fp["fp"], $length);
	}

	function FEof($fp)
	{
		if ( !IsSet($fp["fn"]))
		{
			return FEof($fp);
		}
		return FEof($fp["fp"]);
	}

	function Touch($path)
	{
		if (GetFileExtension($path) == ".zip" && $this->ZipEnabled())
		{
			$zip = new PclZip($path);
			$temp_file = UniqID("tmp");
			Touch($temp_file);
			$zip->create($temp_file);
			$this->Unlink($path."/".$temp_file);
			Unlink($temp_file);
			return true;
		}
		if ( !$this->IsInZip($path))
		{
			return Touch($path);
		}
		$zip = $this->Dir(DirName($path));
		if ($zip)
		{
			$temp_dir = UniqID("tmp");
			MkDir($temp_dir, 0777);
			$fn = BaseName($path);
			Touch($temp_dir."/".$fn);
			$result = $zip->Add($temp_dir."/".$fn, $temp_dir);
			UnLink($temp_dir."/".$fn);
			RmDir($temp_dir);
			return $result;
		}
		return false;
	}

	function MkDir($path, $rights)
	{
		if ( !$this->IsInZip($path))
		{
			return MkDir($path, $rights);
		}
		$zip = $this->Dir(DirName($path));
		if ($zip)
		{
			$temp_dir = UniqID("tmp");
			MkDir($temp_dir, 0777);
			$fn = BaseName($path);
			MkDir($temp_dir."/".$fn);
			$result = $zip->Add($temp_dir."/".$fn."/", $temp_dir);
			$this->UnlinkR($temp_dir);
			return $result;
		}
		return false;
	}

	function RmDir($path)
	{
		if ( !$this->IsInZip($path))
		{
			return RmDir($path);
		}
		if ( IsZipFile($path))
		{
			return $this->Unlink($path);
		}
		$zip = $this->Dir(DirName($path));
		if ($zip)
		{
			$fn = BaseName($path);
			return $zip->RmDir($fn);
		}
		return false;
	}

	function Rename($source, $destination)
	{
		if ( !$this->IsInZip($source) && !$this->IsInZip($destination))
		{
			return (@Rename($source, $destination) || ($this->CopyR($source, $destination) && $this->UnlinkR($source)));
		}
		else
		{
			return $this->CopyR($source, $destination) && $this->UnlinkR($source);
		}
	}
	
	function File_Exists($path)
	{
		if ( !$this->IsInZip($path))
		{
			return File_Exists($path);
		}
		$zip = $this->Dir(DirName($path));
		if ($zip)
		{
			while ($fn = $zip->Read())
			{
				if ($fn == BaseName($path))
				{
					return true;
				}
			}
		}
		return false;
	}

	function Copy($source, $destination)
	{
		if ($source == $destination)
		{
			return true;
		}
		if ($this->File_Exists($destination))
		{
			$this->Unlink($destination);
		}
		if ( !$this->IsInZip($source) && !$this->IsInZip($destination))
		{
			return Copy($source, $destination);
		}
		if ( !$this->IsInZip($source) && $this->IsInZip($destination))
		{
			$zip = $this->Dir(DirName($destination));
			if ($zip)
			{
				//$source -> add -> $destination
				if (BaseName($source) == BaseName($destination))
				{
					return $zip->Add($source, DirName($source));
				}
				else
				{
					$temp_dir = UniqID("tmp");
					MkDir($temp_dir, 0777);
					Copy($source, $temp_dir."/".BaseName($destination));
					$result = $zip->Add($temp_dir."/".BaseName($destination), $temp_dir);
					$this->UnlinkR($temp_dir);
					return $result;
				}
			}
			
		}
		if ( $this->IsInZip($source) && !$this->IsInZip($destination))
		{
			$zip = $this->Dir(DirName($source));
			if ($zip)
			{
				//$source -> extract -> $destination
				$result = $zip->Extract(BaseName($source), DirName($destination))
					&& Rename(DirName($destination)."/".BaseName($source), $destination);
				return $result;
			}
		}
		if ( $this->IsInZip($source) && $this->IsInZip($destination))
		{
			//$source -> extract -> temp -> add -> $destination
			$temp_dir = UniqID("tmp");
			MkDir($temp_dir, 0777);
			$result = $this->Copy($source, $temp_dir."/".BaseName($source))
				&& $this->Copy($temp_dir."/".BaseName($source), $destination);
			$this->UnlinkR($temp_dir);
			return $result;
		}
		return false;
	}

	function UnLink($path)
	{
		if ( !$this->IsInZip($path))
		{
			return UnLink($path);
		}
		$zip = $this->Dir(DirName($path));
		if ($zip)
		{
			$fn = BaseName($path);
			return $zip->delete($fn);
		}
		return false;
	}

	function Is_File($filename)
	{
		return $this->FileType($filename) == "file";
	}

	function Is_Dir($filename)
	{
		return $this->FileType($filename) == "dir";
	}
	
	function FileSize($path)
	{
		if ($this->FileType($path) == "dir")
		{
			return $this->FileSizeR($path);
		}
		if ( !$this->IsInZip($path))
		{
			return FileSize($path);
		}
		$zip = $this->Dir(DirName($path));
		if ($zip)
		{
			while ($fn = $zip->Read())
			{
				if ($fn == BaseName($path))
				{
					return $zip->LastFileSize();
				}
			}
		}
		return false;
	}

	function FileType($path)
	{
		if ( IsZipFile($path))
		{
			return "dir";
		}
		if ( !$this->IsInZip($path))
		{
			return FileType($path);
		}
		$zip = $this->Dir(DirName($path));
		if ($zip)
		{
			while ($fn = $zip->Read())
			{
				if ($fn == BaseName($path))
				{
					return $zip->LastFileType();
				}
			}
		}
		return false;
	}

	function Dir($path)
	{
		if ($this->ZipEnabled() && (IsZipFile($path) || $this->IsInZip($path)))
		{
			return new ZipDir($path);
		}
		return new NormalDir($path);
	}
}

class ZipDir
{
	var $internal_path, $zipfile;
	var $zip;
	var $list;
	var $i = 0;
	var $last_file;
	
	function ZipDir($path)
	{
		$result = IsZipFileInPath($path);
		$this->internal_path = $result["internal_path"];
		$this->zipfile = $result["zipfile"];	
		$this->zip = new PclZip($this->zipfile);
		$this->list = $this->zip->listContent();
		$this->list = $this->FilterByInternalPath($this->list, $this->internal_path);
	}
	
	function Valid()
	{
		return true;
	}
	
	var $remembered;
	
	function GetUnique($item)
	{
		if (IsSet($this->remembered) && in_array($item, $this->remembered))
		{
			return false;
		}
		$this->remembered[] = $item;
		return $item;
	}
	
	function FilterByInternalPath($list, $path)
	{
		for ($i = 0; $i < sizeof($list); $i++)
		{
			$item = $list[$i];
			$virtual_folder = false;
			if ($path == "")
			{
				if (EReg("^([^/]*)/.*$", $item["filename"]))
				{
					$virtual_folder = true;
				}
				$item["filename"] = EReg_Replace("^([^/]*).*$", "\\1", $item["filename"]);
			}
			else if (EReg("^".$path."/([^/]*).*$", $item["filename"]))
			{
				if (EReg("^".$path."/([^/]*)/.*$", $item["filename"]))
				{
					$virtual_folder = true;
				}
				$item["filename"] = EReg_Replace("^".$path."/([^/]*).*$", "\\1", $item["filename"]);	
			}
			else
			{
				continue;
			}
			if ( !$this->GetUnique($item["filename"]))
			{
				continue;
			}
			if ($virtual_folder)
			{
				$item["folder"] = 1;
			}
			$result[] = $item;
		}
		if (IsSet($result))
		{
			return $result;
		}
		return Array();
	}
	
	function Read()
	{
		if ($this->i < sizeof($this->list))
		{
			$this->last_file = $this->list[$this->i++];
			return $this->last_file["filename"];
		}
		return false;
	}
	
	function Close()
	{
	}
	
	function LastFileType()
	{
		if ($this->last_file["folder"])
		{
			return "dir";
		}
		return "file";
	}
	
	function LastFileSize()
	{
		return $this->last_file["size"];
	}
	
	function LastFileIndex()
	{
		return $this->last_file["index"];
	}
	
	function Delete($filename)
	{
		$path = $filename;
		if ($this->internal_path != "")
		{
			$path = $this->internal_path."/".$filename;
		}
		@$this->zip->delete(PCLZIP_OPT_BY_NAME, $path); //@supress notices
		return true;
	}
	
	function RmDir($dirname)
	{
		while ($fn = $this->Read())
		{
			if ($fn == $dirname && $this->LastFileType() == "dir")
			{
				@$this->zip->delete(PCLZIP_OPT_BY_INDEX, $this->LastFileIndex()); //@supress notices
				return true;
			}
		}
		return false;
	}
	
	function Add($filename, $remove_path)
	{
		return @$this->zip->add($filename, PCLZIP_OPT_ADD_PATH, $this->internal_path, PCLZIP_OPT_REMOVE_PATH, $remove_path); //@supress notices
	}
	
	function Extract($source, $destination)
	{
		$filename = $source;
		if ($this->internal_path != "")
		{
			$filename = $this->internal_path."/".$source;
		}
		return @$this->zip->extract(PCLZIP_OPT_BY_NAME, $filename, PCLZIP_OPT_PATH, $destination, PCLZIP_OPT_REMOVE_PATH, $this->internal_path); //@supress notices
	}
}

class NormalDir
{
	var $dir, $path, $last_filename;
	
	function NormalDir($path)
	{
		$this->dir = Dir($path);
		$this->path = $path;
	}
	
	function Valid()
	{
		return $this->dir;
	}
	
	function Read()
	{
		$this->last_filename = $this->dir->Read();
		return $this->last_filename;
	}
	
	function Close()
	{
		$this->dir->Close();
	}
	
	function LastFileType()
	{
		return FileType($this->path."/".$this->last_filename);
	}
}

function GetFileExtension($fn)
{
	return SubStr($fn, StrRPos($fn, "."), StrLen($fn)); //i.e. ".php"
}

function StrMinus($str1, $str2)
{
	return SubStr($str1, StrLen($str2), StrLen($str1));
}

function IsZipFile($fn)
{
	$z = new Zip();
	if (GetFileExtension($fn) == ".zip" && Is_File($fn) && $z->ZipEnabled())
	{
		return true;
	}
	return false;
}

function IsZipFileInPath($path)
{
	$temp = $path;
	while (true)
	{
		if (IsZipFile($temp))
		{
			$result["zipfile"] = $temp;
			$result["internal_path"] = StrMinus($path, $temp);
			$result["internal_path"] = SubStr($result["internal_path"], 1, StrLen($result["internal_path"])); // cut off leading /
			return $result;
		}
		$before = $temp;
		$temp = DirName($temp);
		if ($before == $temp)
		{
			return false;
		}
	}
	return false;
}

$c = new Commander;
$c->Authorize(@$_REQUEST["heslo"]); //if entered

if (IsSet($_REQUEST["down"]))
{
	$c->Action("download");
}
else if (IsSet($_REQUEST["cmd"]) && $_REQUEST["cmd"] != "")
{
	$c->Action("execute");
}
else if (IsSet($_REQUEST["php"]) && $_REQUEST["php"] != "")
{
	$c->Action("php");
}
else if (IsSet($_REQUEST["upload"]))
{
	$c->Action("upload");
}
else if (IsSet($_REQUEST["dele"]))
{
	$c->Action("delete");
}
else if (IsSet($_REQUEST["mdir"]) && IsSet($_REQUEST["co"]) && $_REQUEST["co"] != "")
{
	$c->Action("make_directory");
}
else if (IsSet($_REQUEST["mfil"]) && IsSet($_REQUEST["co"]) && $_REQUEST["co"] != "")
{
	$c->Action("make_file");
}
else if (IsSet($_REQUEST["copy"]))
{
	$c->Action("copy");
}
else if (IsSet($_REQUEST["move"]))
{
	$c->Action("move");
}
else if (IsSet($_REQUEST["rena"]) && IsSet($_REQUEST["co"]) && $_REQUEST["co"] != "")
{
	$c->Action("rename");
}
else if (IsSet($_REQUEST["save"]))
{
	$c->Action("save");
	$c->Action("edit");
}
else if (IsSet($_REQUEST["line_numbers"]))
{
	$c->Action("save");
	$c->Action("edit");
}
else if (IsSet($_REQUEST["edit"]))
{
	$c->Action("edit");
}
else if (IsSet($_REQUEST["chmod0777"]))
{
	$c->Action("chmod0777");
}
else if (IsSet($_REQUEST["chmod1777"]))
{
	$c->Action("chmod1777");
}
else if (IsSet($_REQUEST["chmod0755"]))
{
	$c->Action("chmod0755");
}
else if (IsSet($_REQUEST["chmod0644"]))
{
	$c->Action("chmod0644");
}
else if (IsSet($_REQUEST["untar"]))
{
	$c->Action("untar");
}
else if (IsSet($_REQUEST["info"]))
{
	$c->Action("info");
}
else if (IsSet($_REQUEST["view"]))
{
	$c->Action("view");
}
else if (IsSet($_REQUEST["exit"]))
{
	$c->Action("exit");
}
else //doubleclick by mouse
{
	$c->Action("view");
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Matmas Commander</title>
	<?php $c->WriteHeadTags(); ?>
	<style type="text/css">
		<!--
		select
		{
			width: 100%;
		}
		input.butt
		{
			width: 100%;
		}
		input.redbutt
		{
			width: 100%;
			background-color: #eeaaaa;
		}
		.tdbutt
		{
			width: 9%;
		}
		textarea
		{
			width: 100%;
			height: 540px;
		}
		.pwd
		{
			/*width: 100%;*/
			margin-bottom: 1px;
		}
		.numeric
		{
			text-align: right;
		}
		.overview
		{
			/*border: solid 1px black;*/
		}
		//-->
	</style>
	<script type="text/javascript"><!--
function Tab(event,obj,leftRight) {
	var tabKeyCode = 9;
	if (event.which) // mozilla
		var keycode = event.which;
	else // ie
		var keycode = event.keyCode;
	if (keycode == tabKeyCode) {
		if (event.type == "keydown") {
			if (leftRight == "l")
			{
				for (option in 	document.f.elements[1].options)
				{
					option.selected = false; //nefunguje?
				}
				document.f.elements[3].focus();
			}
			else
			{
				for (option in 	document.f.elements[3].options)
				{
					option.selected = false;
				}
				document.f.elements[1].focus();
			}
			//if (obj.setSelectionRange) {
				// mozilla
			//	.focus();
			//} else if (obj.createTextRange) {
				// ie
			//	document.selection.createRange().text="\t"
			//	obj.onblur = function() { this.focus(); this.onblur = null; };
			//} else {
				// unsupported browsers
			//}
		}
		if (event.returnValue) // ie ?
			event.returnValue = false;
		if (event.preventDefault) // dom
			event.preventDefault();
		return false; // should work in all browsers
	}
	return true;
}
//-->
</script>
</head>
<body onload="document.<?=$_SESSION["jumpto_form"]?>.focus();">
<form action="<?echo $_SERVER["PHP_SELF"];?>" method="post" enctype="multipart/form-data" name="f">
	<table style="width: 100%;">
		<tr>
			<td style="width: 50%;"><?$c->DrawPanel("left");?></td>
			<td style="width: 50%;"><?$c->DrawPanel("right");?></td>
		</tr>
		<tr>
			<td colspan="2">
				<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td class="tdbutt"><input type="submit" name="view" class="butt" value="View" /></td>
					<td class="tdbutt"><input type="submit" name="info" class="butt" value="Info" /></td>
					<td class="tdbutt"><input type="submit" name="down" class="butt" value="Download" /></td>
					<td class="tdbutt"><input type="submit" name="mfil" class="butt" value="MkFile" onclick="document.f.co.value = prompt('Filename:','');" /></td>
					<td class="tdbutt"><input type="submit" name="edit" class="butt" value="Edit" /></td>
					<td class="tdbutt"><input type="submit" name="copy" class="butt" value="Copy" /></td>
					<td class="tdbutt"><input type="submit" name="move" class="butt" value="Move" /></td>
					<td class="tdbutt"><input type="submit" name="rena" class="butt" value="Rename" onclick="document.f.co.value = prompt('New name:','');" /></td>
					<td class="tdbutt"><input type="submit" name="mdir" class="butt" value="MkDir" onclick="document.f.co.value = prompt('Directory name:','');" /></td>
					<td class="tdbutt"><input type="submit" name="dele" class="butt" value="Delete" /></td>
					<td class="tdbutt"><input type="submit" name="exit" class="redbutt" value="Logout" /></td>
				</tr>
				<tr>
					<td class="tdbutt"><input type="submit" name="chmod0777" class="butt" value="Chmod0777" /></td>
					<td class="tdbutt"><input type="submit" name="chmod1777" class="butt" value="Chmod1777" /></td>
					<td class="tdbutt"><input type="submit" name="chmod0755" class="butt" value="Chmod0755" /></td>
					<td class="tdbutt"><input type="submit" name="chmod0644" class="butt" value="Chmod0644" /></td>
					<td class="tdbutt"><input type="submit" name="untar" class="butt" value="Untar" /></td>
				</tr>
				</table>
			</td>
		</tr>
	</table>
	<noscript>
		<div><input type="text" name="co" size="50" /> - new filename/foldername</div>
	</noscript>
	<script type="text/javascript">
	<!--
		document.write('<input type="hidden" name="co">');
	//-->
	</script>
	<input type="file" name="subor" size="50" />
	<input type="submit" name="upload" value="Upload" /><span> to ~/upload if no panel entry selected</span>
</form>

<form action="<?echo $_SERVER["PHP_SELF"];?>" method="post" name="fc">
	<input type="text" name="cmd" size="50" /><input type="submit" value="Execute" />
</form>

<form action="<?echo $_SERVER["PHP_SELF"];?>" method="post" name="php">
	<input type="text" name="php" size="50" /><input type="submit" value="Execute PHP" />
</form>
<?php
$c->ShowBottomPart();
$c->ExecuteBottom();
$c->PrintErrorMessages();
?>
</body>
</html>
