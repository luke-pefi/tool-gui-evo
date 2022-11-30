<?php

/**
 *
 * DropboxConnector - provides support for accessing the Precision EFI Dropbox account.  We use the SDK provided by:
 *
 *    https://github.com/kunalvarma05/dropbox-php-sdk
 *
 */

namespace api;

use ClassTemplate\File;
use League\Route\Http\Exception;
use Monolog\Logger;

use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\Models\FolderMetadata;
use Kunnu\Dropbox\Models\FileMetadata;
use Kunnu\Dropbox\Models\DeletedMetadata;
use Kunnu\Dropbox\DropboxFile;

use util\LoggingTrait;
use util\StatusTrait;
use util\RaspberryInfo;

use GuzzleHttp\Exception\ClientException;

class DropboxConnector {

  /* bring in logging and status behaviors */

  use LoggingTrait;
  use StatusTrait;

  protected $factoryDefault = [
    'folder' => 'Precision EFI Support Production',
    'key'    => 'yekhxrueqdhxtdy',
    'secret' => 'cv5gmxgo6zmnzsw',
    'token'  => 'LLXLD0D9ChAAAAAAAAAAEC1zOXP_qmrgKuuRdnH5BhTWsMwutC4TtsDn_vir95bq',
    'work'   => '/tmp/support-upload',
    'user'   => 'anonymous'
  ];

  /** @var DropboxApp the client configuration for Dropbox */

  private $app              = null;

  /** @var Dropbox the actual SDK */

  private $dropbox          = null;

  /** @var object our account info. */

  private $account          = null;

  /**
   * DropboxConnector constructor.
   *
   * @param array $config - any options you want to override
   * @param Logger $logger - Monolog for logging if you want it.
   *
   */

  public function __construct($config=null, $logger=null) {

    $this->debug(get_class()."() starts...");

    /* connect to the system log */

    $this->setLogger($logger);

    $this->unReady();

    /* configure for the database */

    if ($config !== null) {

      if (is_array($config)) {
        $this->factoryDefault = array_replace($this->factoryDefault, $config);
      } else {
        $this->setError(get_class()."() - expecting an array for configuration.");
        return;
      }
    }

    if(isset($this->factoryDefault['user'])) {
      $this->factoryDefault['work'] = $this->factoryDefault['work'].'-'.$this->factoryDefault['user'];
    }

    /* try to configure a Dropbox app... */

    try {

      $this->debug(get_class()."() creating client...");

      $this->app     =
        new DropboxApp($this->factoryDefault['key'],
                       $this->factoryDefault['secret'],
                       $this->factoryDefault['token']);

      $this->dropbox = new Dropbox($this->app);

    } catch (ClientException $e) {

      $response = $e->getResponse();
      $msg      = $response->getBody()->getContents();

      $this->setError(get_class()."() client setup error? $msg");

    } catch (Exception $e) {

      $this->setError(get_class()."() client creation exception: ".$e->getMessage());
      return ;
    }

    $account = false;

    try {

      $this->debug(get_class()."() fetching account...");

      $account = $this->dropbox->getCurrentAccount();

    } catch (ClientException $e) {

      $response = $e->getResponse();
      $msg      = $response->getBody()->getContents();

      $this->setError(get_class()."() getCurrentAccount() transport/authorization error? $msg");

    } catch (\Exception $e) {

      $this->setError(get_class()."() getCurrentAccount() exception: ".$e->getMessage());
    }

    if(!is_object($account)) {
      $this->setError(get_class()."() - can not fetch account data.");
      return ;
    }

    $this->account = (object)[
      'id'      => $account->getAccountId(),
      'name'    => $account->getDisplayName(),
      'email'   => $account->getEmail(),
      'picture' => $account->getProfilePhotoUrl(),
      'type'    => $account->getAccountType()
    ];

    $this->debug(get_class()."() - found dropbox account: ".print_r($this->account, true));

    /* if we get this far, we are good to go! */

    $this->makeReady();
  }

  /**
   *
   * getAccount() - fetch a simplified version of account info (PHP object).
   *
   * @return mixed exactly false on error, otherwise the PHP object with the account info.
   *
   */

  public function getAccount() {

    if(!$this->isReady()) {

      $this->setError(get_class()."::getAccount() - API not ready");
      return false;
    }

    return $this->account;
  }

  public function cleanpath($path) {

    $path = trim($path);
    $path = rtrim($path, '/');

    return $path;
  }

  /**
   *
   * is_file() check to see if the file exists
   *
   * @param string $path - the folder to check
   *
   * @return boolean exactly true if it exists.
   *
   */

  public function is_file($path) {

    $path = $this->cleanpath($path);

    if(!$this->isReady()) {

      $this->setError(get_class()."::is_file() - API not ready");
      return false;
    }

    if($path == "/") {

      /* root always exists and is a folder */

      return true;
    }

    $info = $this->fileinfo($path);

    if(!$info) {

      $this->setError(get_class()."::is_file() - no such file ($path)");
      return false;
    }

    if(!$info->is_file) {

      $this->setError(get_class()."::is_file() - not a file ($path)");
      return false;
    }

    return true;
  }

  /**
   *
   * is_dir() check to see if the folder exists
   *
   * @param string $path - the folder to check
   *
   * @return boolean exactly true if it exists.
   *
   */

  public function is_dir($path) {

    $path = $this->cleanpath($path);

    if(!$this->isReady()) {

      $this->setError(get_class()."::is_dir() - API not ready");
      return false;
    }

    if($path == "/") {

      /* root always exists and is a folder */

      return true;
    }

    $info = $this->fileinfo($path);

    if(!$info) {

      $this->setError(get_class()."::is_dir() - no such directory ($path)");
      return false;
    }

    if(!$info->is_dir) {

      $this->setError(get_class()."::is_dir() - not a directory ($path)");
      return false;
    }

    return true;
  }

  /**
   *
   * file_exists() check to see if the file or folder exists
   *
   * @param string $path - the file to check
   *
   * @return boolean exactly true if it exists.
   *
   */

  public function file_exists($path) {

    $path = $this->cleanpath($path);

    if(!$this->isReady()) {

      $this->setError(get_class()."::file_exists() - API not ready");
      return false;
    }

    if($path == "/") {

      /* root always exists */

      return true;
    }

    $info = $this->fileinfo($path);

    if(!$info) {

      $this->setError(get_class()."::file_exists() - no such file ($path)");
      return false;
    }

    return true;
  }

  /**
   *
   * mkdir() - recursively create a directory; ensure it exists.
   *
   * @param string $path - the path to make (recursively if needed)
   *
   * @return boolean exactly true on success
   *
   */

  public function mkdir($path) {

    $path = $this->cleanpath($path);

    $this->debug(get_class()."::mkdir() starts ($path)...");

    if(!$this->isReady()) {

      $this->setError(get_class()."::mkdir() - API not ready");
      return false;
    }

    if($path == "/") {

      /* nothing to do */

      return true;
    }

    /*
     * break out into the folders we need to descend into, and do it...
     *
     */

    $components = explode(DIRECTORY_SEPARATOR, $path);
    $dir        = '';

    foreach ($components as $part) {

      $dir   .= DIRECTORY_SEPARATOR.$part;

      if($dir == '/') {

        /* its already there. */

        $dir = '';
        $this->debug(get_class()."::mkdir() [/] exists.");
        continue;
      }

      $info   = $this->fileinfo($dir);

      $this->debug(get_class()."::mkdir() creating: $dir ...");

      /* does it exist already? */

      if($info !== false) {

        /* its already there. */

        $this->debug(get_class()."::mkdir() [$dir] exists.");
        continue;
      }

      /* is it file and not a folder? (we can't complete the request) */

      if(is_file($dir)) {
        $this->setError(get_class()."::mkdir() - can't make folder ($dir) its already a file.");
        return false;
      }

      /* needs to be created */

      $folder = $this->dropbox->createFolder($dir);

      if(!is_object($folder)) {

        /* we couldn't create it. */

        $this->setError(get_class()."::mkdir() - there was a problem creating a required folder ($dir)");
        return false;
      }

      $this->debug(get_class()."::mkdir() creating: . created");

    }

    /* if we get this far, then its been created. */

    return true;
  }

  /**
   *
   * fileinfo() - fetch the meta information of the given folder/path
   *
   * @param string $path path to the file ot get any meta/attributes of
   *
   * @return mixed exactly false on error, otherwise PHP object with the meta data.
   *
   */

  public function fileinfo($path) {

    $path = $this->cleanpath($path);

    if(!$this->isReady()) {

      $this->setError(get_class()."::fileinfo() - API not ready");
      return false;
    }

    if($path == '/') {

      return (object)[
        'id'       => 0,
        'path'     => '/',
        'size'     => 0,
        'modified' => false,
        'is_dir'   => true,
        'is_file'  => false
      ];
    }

    /* try to fetch... */

    $file = false;
    $info = (object)[
      'id'       => false,
      'path'     => false,
      'size'     => 0,
      'modified' => false,
      'is_dir'   => false,
      'is_file'  => false
    ];

    try {

      $this->debug(get_class()."::fileinfo() fetching meta on $path...");

      $file = $this->dropbox->getMetadata($path, ["include_media_info" => true]);

      $account = $this->dropbox->getCurrentAccount();

    } catch (ClientException $e) {

      $response = $e->getResponse();
      $msg      = $response->getBody()->getContents();

      $this->setError(get_class()."::fileinfo() transport/authorization error? $msg");

      return false;

    } catch (\Exception $e) {

      $this->setError(get_class()."::fileinfo() exception: ".$e->getMessage());

      $err = json_decode($e->getMessage());

      if(is_object($err)) {

        if(isset($err->error_summary) && preg_match('/not_found/', $err->error_summary)) {
          $this->setError(get_class()."::fileinfo() path not found ($path)");
          return false;
        }
      }

      return false;
    }

    if(!is_object($file)) {

      $this->setError(get_class()."::fileinfo() - can not find file/folder ($path).");
      return false;
    }

    /* convert to more human readable version... */

    $info->id        = $file->getId();

    $tag             = $file->getDataProperty('.tag');


    if(($tag == 'folder') || ($file instanceof FolderMetadata)) {

      $info->is_dir  = true;
      $info->is_file = false;

    } else {

      $info->is_dir  = false;
      $info->is_file = true;
    }

    $info->size      = $file->getDataProperty('size');
    $info->path      = $file->getPathDisplay();
    $info->modified  = $file->getDataProperty('client_modified');

    return $info;
  }

  /**
   *
   * copy() - copy (upload) a file to drop box.
   *
   * @param string $localPath
   * @param string $dropboxPath
   *
   * @return mixed exactly false on error.
   *
   */

  public function copy($localPath, $dropboxPath) {

    $dropboxPath = $this->cleanpath($dropboxPath);

    $this->debug(get_class()."::copy() src=$localPath >> dst=$dropboxPath ...");

    if(!$this->isReady()) {

      $this->setError(get_class()."::copy() - API not ready");
      return false;
    }

    /* make a file wrapper that can be used to strea/upload to dropbox... */

    if(!is_file($localPath)) {

      $this->setError(get_class()."::copy() - no such file ($localPath).");
      return false;
    }

    if(!is_readable($localPath)) {

      $this->setError(get_class()."::copy() - can't read file ($localPath).");
      return false;
    }

    $fileWrapper = new DropboxFile($localPath);
    $file        = false;

    /* make sure its folder exists */

    $dir         = dirname($dropboxPath);

    if(!$this->mkdir($dir)) {

      $this->setError(get_class()."::copy() - can't make required folder: ".$this->getError());
      return false;
    }

    /* double check if they are trying to overwrite a folder */

    if(is_dir($dropboxPath)) {

      $this->setError(get_class()."::copy() - can't overwrite an existing folder ($dropboxPath). ");
      return false;
    }

    /* attempt the upload */

    $this->debug(get_class()."::copy() upload starts...");

    try {

      $file = $this->dropbox->upload($fileWrapper, $dropboxPath, ['mode' => 'overwrite']);

    } catch (ClientException $e) {

      $response = $e->getResponse();
      $msg      = $response->getBody()->getContents();

      $this->setError(get_class()."::copy() transport/authorization error? $msg");

      return false;

    } catch (\Exception $e) {

      $this->setError(get_class() . "::copy() exception: " . $e->getMessage());

      $err = json_decode($e->getMessage());

      if (is_object($err)) {

        if (isset($err->error_summary)) {

          $this->setError(get_class() . "::copy() dropbox error ({$err->error_summary})");
        }
      }

      return false;

    }

    if(!($file instanceof FileMetadata)) {

      $this->setError(get_class() . "::copy() did not actually upload the file.");
      return false;
    }

    /* ok, refresh the info for this file */

    $this->debug(get_class()."::copy() uploaded!");

    return $this->fileinfo($dropboxPath);
  }

  /**
   *
   * ls() - list the contents of folder or fetch info for a file.
   *
   * @param string $path the directory/file to list.
   *
   * @return mixed exactly false on error, otherwise the list of simplified PHP objects, one for each item.
   *
   */

  public function ls($path) {

    $path = $this->cleanpath($path);

    $this->debug(get_class()."::ls() $path starts...");

    if(!$this->isReady()) {

      $this->setError(get_class()."::ls() - API not ready");
      return false;
    }

    /* before doing a folder listing, if it's a file, just return that... */

    $file       = $this->fileinfo($path);

    if(is_object($file) && ($file->is_file == true)) {

      /* early exit, it's just a file. */

      return $file;
    }

    if($file === false) {

      /* no such folder? */

      $this->setError(get_class()."::ls() - no such directory ($path).");
      return false;
    }

    $items      = false;

    try {

      $contents = $this->dropbox->listFolder($path);
      $items    = $contents->getItems()->all();

    } catch (ClientException $e) {

      $response = $e->getResponse();
      $msg      = $response->getBody()->getContents();

      $this->setError(get_class()."::ls() transport/authorization error? $msg");

      return false;

    } catch (\Exception $e) {

      $this->setError(get_class() . "::ls() exception: " . $e->getMessage());

      $err = json_decode($e->getMessage());

      if (is_object($err)) {

        if (isset($err->error_summary)) {

          $this->setError(get_class() . "::ls() dropbox error ({$err->error_summary})");
        }
      }

      return false;
    }

    if($items === false) {

      $this->setError(get_class()."::ls() - could not get file list from dropbox.");
      return false;
    }

    $results    = [];

    foreach($items as $item) {

      if(!($item instanceof FolderMetadata) && !($item instanceof FileMetadata)) {

        $this->setError(get_class()."::ls() - garbled directory entry.");
        return false;
      }

      $info            = (object)[
        'id'           => false,
        'path'         => false,
        'size'         => 0,
        'modified'     => false,
        'is_dir'       => false,
        'is_file'      => false
      ];

      $info->id        = $item->getId();
      $tag             = $item->getDataProperty('.tag');

      if(($tag == 'folder') || ($item instanceof FolderMetadata)) {

        $info->is_dir  = true;
        $info->is_file = false;

      } else {

        $info->is_dir  = false;
        $info->is_file = true;
      }

      $info->size      = $item->getDataProperty('size');
      $info->path      = $item->getPathDisplay();
      $info->modified  = $item->getDataProperty('client_modified');

      $results[] = $info;
    }

    /* pass 'em back */

    return $results;
  }

  /**
   *
   * rm() recursively delete folder/file.
   *
   * @param string $path the folder/file to remove (recursively)
   *
   * @return boolean exactly false on error.
   *
   */

  public function rm($path) {

    $path        = $this->cleanpath($path);

    $this->debug(get_class()."::rm() $path starts...");

    if(!$this->isReady()) {

      $this->setError(get_class()."::rm() - API not ready");
      return false;
    }

    $deleted     = false;

    try {

      $meta      = $this->dropbox->delete($path);

      if(($meta instanceof FileMetadata)||($meta instanceof FolderMetadata)||($meta instanceof DeletedMetadata)) {
        $deleted = true;
      }

    } catch (ClientException $e) {

      $response  = $e->getResponse();
      $msg       = $response->getBody()->getContents();

      $this->setError(get_class()."::rm() transport/authorization error? $msg");

      return false;

    } catch (\Exception $e) {

      $this->setError(get_class() . "::rm() exception: " . $e->getMessage());

      $err = json_decode($e->getMessage());

      if (is_object($err)) {

        if (isset($err->error_summary)) {

          $this->setError(get_class() . "::rm() dropbox error ({$err->error_summary})");
        }
      }

      return false;
    }

    if(!$deleted) {

      $this->setError(get_class() . "::rm() did not actually delete.");
      return false;
    }

    /* all done */

    return true;
  }

  /**
   *
   * findSupportLogs() fetch the list of files on the Raspberry PI that we would want to upload.
   *
   * @param string $workArea - the temporary folder to gather up support info in.
   *
   * @return mixed exactly false on error, othwise the list of support info/log files.
   *
   */

  public function findSupportLogs($workArea=false) {

    if(!$workArea) {
      $workArea = $this->factoryDefault['work'];
    }

    @mkdir($workArea);

    if(!is_dir($workArea) || !is_readable($workArea)) {
      $this->setError(get_class() . "::findSupportLogs() no usable work area ($workArea).");
      return false;
    }

    /* generate a file for RPI hardware config */

    $rpiInfo = RaspberryInfo::info();
    $text    = json_encode($rpiInfo);
    $output  = "$workArea/rpi-info.json";

    file_put_contents($output, $text);

    /* top snapshot */

    $output  = "$workArea/top.txt";
    `/usr/bin/top -n 1 -b > $output`;

    /* ps snapshot */

    $output  = "$workArea/ps.txt";
    `/bin/ps -ef > $output`;

    /* vmstat snapshot */

    $output  = "$workArea/ifconfig.txt";
    `/sbin/ifconfig > $output`;

    /* ifconfig snapshot */

    $output  = "$workArea/vmstat.txt";
    `/usr/bin/vmstat -w 1 5 > $output`;

    /* CAN status snapshot */

    $output  = "$workArea/can-status.txt";
    `sudo ip -s -d link show can0 > $output`;

    /* get any screen shots we took during flashing */

    `sudo /usr/bin/rsync -avz /tmp/*.png $workArea/.`;

    /* the CAN daemon log */

    `sudo /usr/bin/rsync -avz /home/mgarvin/can-daemon/logs/. $workArea/.`;

    /* the php/rest logs */

    `sudo /usr/bin/rsync -avz /home/mgarvin/public_html/logs/. $workArea/.`;

    /* critical system logs */

    `/bin/dmesg > $workArea/dmesg.txt`;

    `sudo /usr/bin/tail -c 10485760 /var/log/daemon.log > $workArea/daemon.log`;

    `sudo /usr/bin/tail -c 10485760 /var/log/syslog > $workArea/sys.log`;

    /* grab a log of daemon startup ... */

    `sudo /bin/systemctl status ecu.service > $workArea/ecu.status`;
    `sudo /bin/journalctl --no-pager -u ecu.service > $workArea/ecu.journal.txt`;

    `sudo /bin/systemctl status canwatcher.service > $workArea/canwatcher.status`;
    `sudo /bin/journalctl --no-pager -u canwatcher.service > $workArea/canwatcher.journal.txt`;

    /* overall status for systemd */

    `sudo systemctl --no-page status > $workArea/systemd.status.txt`;

    /* capture any insatll logs */

    `sudo /usr/bin/rsync -avz /home/mgarvin/*.log $workArea/. 2>&1`;

    $files = [];

    if ($handle = opendir($workArea)) {

      while (false !== ($entry = readdir($handle))) {

        if ($entry != "." && $entry != "..") {

          $files[] = $workArea.'/'.$entry;
        }
      }

      closedir($handle);
    }

    /* pass 'em back */

    return $files;
  }

  /**
   *
   * createSupportTarBall() - generate and bundle up all the support info into a file we can upload to
   * dropbox.
   *
   * @param string $workArea - the temporary folder to gather up support info in.
   *
   * @return mixed exactly false on error, otherwise the path to the support tar ball.
   *
   */

  public function createSupportTarBall($workArea=false) {

    $this->debug(get_class() . "::createSupportTarBall() starts...");

    if(!$this->findSupportLogs($workArea)) {

      $this->setError(get_class() . "::createSupportTarBall() can not capture logs: ".$this->getError());
      return false;
    }

    $user     = 'anonymous';

    if(isset($this->factoryDefault['user'])) {
      $user = $this->factoryDefault['user'];
    }

    $output   = "/tmp/support-$user-".date('Y-m-d_H:i:s').".tbz2";

    if(!$workArea) {
      $workArea = $this->factoryDefault['work'];
    }

    if(!is_dir($workArea) || !is_readable($workArea)) {
      $this->setError(get_class() . "::createSupportTarBall() no usable work area ($workArea).");
      return false;
    }

    @unlink($output);

    $cmd = "tar -cvjf $output -C $workArea . 2>&1";

    $this->debug(get_class() . "::createSupportTarBall() packageing: $cmd ...");

    `$cmd`;

    if(!is_file($output)) {

      $this->setError(get_class() . "::createSupportTarBall() did not create tar ball ($output).");
      return false;
    }

    /* all good! */

    $this->debug(get_class() . "::createSupportTarBall() tarball ready ($output).");

    return $output;
  }

  /**
   *
   * sendLogs() automatically gather up all the supprt info/logs and then send them to
   * Dropbox so we can do any analysis we need to do.
   *
   * @param string $workArea - the temporary folder to gather up support info in.
   *
   * @return boolean exactly false on error.
   *
   */

  public function sendLogs($workArea=false) {

    $this->debug(get_class() . "::sendLogs() starts...");

    /* get the support pack to upload ... */

    $this->debug(get_class() . "::sendLogs() gathering info...");

    $toUpload = $this->createSupportTarBall($workArea);

    if(!is_file($toUpload)) {

      $this->setError(get_class() . "::sendLogs() no file to send: ".$this->getError());
      return false;
    }

    /* send it! */

    $user        = 'anonymous';

    if(isset($this->factoryDefault['user'])) {
      $user = $this->factoryDefault['user'];
    }

    $dropboxPath = "/support/uploads/$user/".basename($toUpload);

    $this->debug(get_class() . "::sendLogs() gathering sending src:$toUpload => dst:$dropboxPath ...");

    $info = $this->copy($toUpload, $dropboxPath);

    if(!is_object($info)) {

      $this->setError(get_class() . "::sendLogs() upload failed: ".$this->getError());
      return false;
    }

    /* all done! */

    $this->debug(get_class() . "::sendLogs() uploaded ($dropboxPath).");

    return true;
  }

}

?>
