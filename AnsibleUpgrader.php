<?php

class AnsibleUpgrader {
  private $blnVerbose = true;
  private $strTmpDir = '/tmp/test/';
  private $strDirectory = __DIR__;
  private $arrFiles = [];

  private $arrUpdates = [];

  private $arrPatterns = [];
  private $arrReplaces = [];

  public function __construct() {
    if (!is_dir($this->strTmpDir)) {
      mkdir($this->strTmpDir);
    }

    $this->registerUpdate(
      '/(\s*with_items\:\s+)([a-zA-Z0-9\_\-\.]+)(\s*\n)/',
      '${1}"{{ ${2} }}"${3}'
    );

    $this->registerUpdate(
      '/(\s*)(sudo)(\:\s+)(yes|no|true|false)(\s*\n)/i',
      '${1}become${3}${4}${5}'
    );

    $this->registerUpdate(
      '/(\s*)(sudo_user)(\:\s+[a-zA-Z0-9\_\-\.]+\s*\n)/i',
      '${1}become_user${3}'
    );
  }

  public function run($strDirectory = false) {
    if ($strDirectory !== false) {
      $this->setDirectory($strDirectory);
    }

    $this->arrFiles = $this->loadFilesFromDir($this->strDirectory, 'yml');

    foreach ($this->arrFiles as $strFile) {
      // $this->outMsg("Processing file: $strFile");

      $strContents = file_get_contents($strFile);

      $strUpdated = preg_replace($this->arrPatterns, $this->arrReplaces, $strContents);

      if ($strUpdated !== $strContents) {
        $strTmpFile = $this->strTmpDir . basename($strFile);

        file_put_contents($strTmpFile, $strUpdated);

        $strDiff = `diff $strFile $strTmpFile`;

        file_put_contents($strFile, $strUpdated);

        $this->outMsg("\n\nFile has been updated: $strFile\n$strDiff");
      }
    }
  }

  public function getDirectory() {
    return $this->strDirectory;
  }

  public function setDirectory($strDirectory) {
    if (is_string($strDirectory) && trim($strDirectory) !== '' && is_dir($strDirectory)) {
      $this->strDirectory = $strDirectory;
    } else {
      throw new Exception("Invalid directory: $strDirectory");
    }
  }

  private function loadFilesFromDir($strDir, $strExt = false) {
    $arrFiles = [];

    if (is_string($strExt) && trim($strExt) !== '' && $strExt[0] === '.') {
      $strExt = substr($strExt, 1);
    }

    // $this->outMsg("Loading files from dir: $strDir" . ($strExt ? " with extension: $strExt" : ''));

    if (is_dir($strDir)) {
      foreach (scandir($strDir) as $strFile) {
        if ($strFile !== '.' && $strFile !== '..') {
          $strFullPath = $strDir . '/' . $strFile;

          $arrFileInfo = pathinfo($strFullPath);

          if (is_dir($strFullPath)) {
            $arrFiles = array_merge($arrFiles, $this->loadFilesFromDir($strFullPath, $strExt));
          } else if (is_string($strExt) && trim($strExt) !== '') {
            if (strtolower($arrFileInfo['extension']) === strtolower($strExt)) {
              $arrFiles[] = $strFullPath;
            }
          } else {
            $arrFiles[] = $strFullPath;
          }
        }
      }
    }

    return $arrFiles;
  }

  private function registerUpdate($strPattern, $strReplace) {
    $this->arrPatterns[] = $strPattern;
    $this->arrReplaces[] = $strReplace;
  }

  private function outMsg($str) {
    if ($this->blnVerbose) {
      echo "$str\n";
    }
  }
}

$objUpgrader = new AnsibleUpgrader();

$objUpgrader->run();
