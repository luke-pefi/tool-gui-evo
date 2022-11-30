<?php

/**
 *
 * error() - show an error message
 *
 * @param $msg
 *
 */

function error($msg) {

  echo "[cleanlogs][ERROR} $msg\n";

}

/**
 *
 * info() - show an info level message
 *
 * @param $msg
 *
 */

function info($msg) {

  echo "[cleanlogs][INFO} $msg\n";
}

/**
 *
 * debug() - show an debug level message
 *
 * @param $msg
 *
 */

function debug($msg) {

  echo "[cleanlogs][DEBUG} $msg\n";
}

/**
 *
 * cleanLogFile() - helper to clean up a log file by shrinking it to a fixed size when it
 * gets big.
 *
 * @param $fileName
 *
 */

function cleanLogFile($fileName) {

  /* our per file limit is 10 MB */

  $limit    = 10*1024*1024;
  $size     = filesize($fileName);

  if(!is_file($fileName)) {
    error("There is no $fileName file.");
    return false;
  }

  if($size < $limit) {
    info("No cleanup needed for $fileName");
    return true;
  }

  info("Cleaning $fileName ($size => $limit)");

  $cmd      = "tail -c $limit $fileName > /tmp/trimmed";

  info("Trimming: $cmd");

  $output1  = `$cmd`;

  $cmd      = "cat /tmp/trimmed > $fileName";

  info("Replacing: $cmd");

  $output2  = `$cmd`;

  if(!file_exists($fileName)) {
    error("File disappeared? ($fileName)");
  }

  clearstatcache();

  $size     = filesize($fileName);

  if($size > $limit) {
    error("File did not shrink enough? ($fileName: $size > $limit)");
  }

}

/**
 *
 * M A I N
 *
 * clean logs in the given folders.
 *
 */

$folders = [
  '/home/mgarvin/public_html/logs',
  '/var/log'
];

info("Cleaning logs...");

foreach($folders as $folder) {

  info("Checking folder: $folder");

  foreach(glob($folder.'/*') as $file) {

    /* don't worry about sub-folders, or . or .. */

    if(is_dir($file)) {

      continue;
    }

    /* clean it */

    cleanLogFile($file);

  }

}

info("Done.");

?>