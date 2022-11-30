<?php

/**
 *
 * RaspberrInfo - convenience for fetchign rasperry configuration details 
 *
 */

namespace util;

class RaspberryInfo {

  /* revision table @link http://elinux.org/RPi_HardwareHistory#Board_Revision_History */

  public static $revisions = [
    'null'    => ['Revision', 'Release Date', 'Model',            'PCB Revision', 'Memory', 'Notes'          ],
    'Beta'    => ['Beta',     'Q1 2012', 	    'B (Beta)',         '?',            '256 MB', 'Beta Board'     ],
    '0002'    => ['0002',     'Q1 2012', 	    'B',                '1.0',          '256 MB',                  ],
    '0003'    => ['0003',     'Q3 2012',      'B (ECN0001)',      '1.0',          '256 MB', 'Fuses mod and D14 removed'],
    '0004'    => ['0004', 	  'Q3 2012', 	    'B',                '2.0',          '256 MB', '(Mfg by Sony)'  ],
    '0005'    => ['0005',     'Q4 2012',      'B',                '2.0',          '256 MB', '(Mfg by Qisda)' ],
    '0006'    => ['0006',     'Q4 2012',      'B',                '2.0',          '256 MB', '(Mfg by Egoman)'],
    '0007'    => ['0007',     'Q1 2013',      'A',                '2.0',          '256 MB', '(Mfg by Egoman)'],
    '0008'    => ['0008',     'Q1 2013',      'A',                '2.0',          '256 MB', '(Mfg by Sony)'  ],
    '0009'    => ['0009',     'Q1 2013', 	    'A',                '2.0',          '256 MB', '(Mfg by Qisda)' ],
    '000d'    => ['000d',     'Q4 2012',      'B',                '2.0',          '512 MB', '(Mfg by Egoman)'],
    '000e'    => ['000e',     'Q4 2012',      'B',                '2.0',          '512 MB', '(Mfg by Sony)'  ],
    '000f'    => ['000f',     'Q4 2012',      'B',                '2.0',          '512 MB', '(Mfg by Qisda)' ],
    '0010'    => ['0010',     'Q3 2014',      'B+',               '1.0',          '512 MB', '(Mfg by Sony)'  ],
    '0011'    => ['0011',     'Q2 2014',      'Compute Module 1', '1.0',          '512 MB', '(Mfg by Sony)'  ],
    '0012'    => ['0012',     'Q4 2014',      'A+',               '1.1',          '256 MB', '(Mfg by Sony)'  ],
    '0013'    => ['0013',     'Q1 2015',      'B+',               '1.2',          '512 MB', '?'              ],
    '0014'    => ['0014',     'Q2 2014',      'Compute Module 1', '1.0',          '512 MB', '(Mfg by Embest)'],
    '0015'    => ['0015',     '?',            'A+',               '1.1', '256 MB / 512 MB', '(Mfg by Embest)'],
    'a01040'  => ['a01040',   'Unknown',      '2 Model B',        '1.0',          '1 GB',   '(Mfg by Sony)'  ],
    'a01041'  => ['a01041', 	'Q1 2015',      '2 Model B',        '1.1',          '1 GB',   '(Mfg by Sony)'  ],
    'a21041'  => ['a21041',   'Q1 2015',      '2 Model B',        '1.1',          '1 GB',   '(Mfg by Embest)'],
    'a22042'  => ['a22042',   'Q3 2016',      '2 Model B (with BCM2837)', '1.2',  '1 GB',   '(Mfg by Embest)'],
    '900021'  => ['900021',   'Q3 2016',      'A+',               '1.1',          '512 MB', '(Mfg by Sony)'  ],
    '900032'  => ['900032',   'Q2 2016?',     'B+',               '1.2',          '512 MB', '(Mfg by Sony)'  ],
    '900092' 	=> ['900092',   'Q4 2015',      'Zero',             '1.2',          '512 MB', '(Mfg by Sony)'  ],
    '900093'  => ['900093',   'Q2 2016',      'Zero',             '1.3',          '512 MB', '(Mfg by Sony)'  ],
    '920093'  => ['920093',   'Q4 2016?',     'Zero',             '1.3',          '512 MB', '(Mfg by Embest)'],
    '9000c1'  => ['9000c1',   'Q1 2017',      'Zero W',           '1.1',          '512 MB', '(Mfg by Sony)'  ],
    'a02082'  => ['a02082',   'Q1 2016',      '3 Model B',        '1.2',          '1 GB',   '(Mfg by Sony)'  ],
    'a020a0'  => ['a020a0',   'Q1 2017', 'Compute Module 3 (and CM3 Lite)', '1.0', '1 GB',  '(Mfg by Sony)'  ],
    'a22082'  => ['a22082',   'Q1 2016',      '3 Model B',        '1.2',          '1 GB',   '(Mfg by Embest)'],
    'a32082'  => ['a32082',   'Q4 2016',      '3 Model B',        '1.2',          '1 GB',   '(Mfg by Sony Japan)']
  ];

  /**
   *
   * info() - fetch a simplified PHP object that has any device details we
   * would want for registering with the main site.
   *
   */

  public static function info() {

    $serial   = trim(`cat /proc/cpuinfo | grep Serial | cut -d ' ' -f 2`);
    $cpuSpeed = trim(`lscpu |grep -oP 'CPU\s+max\s+MHz:\s+\K.*$'`);
    $cpuModel = trim(`cat /proc/cpuinfo | grep -oP 'model\s+name\s+:\s+\K.*$' | head -1`);
    $numCores = trim(`cat /proc/cpuinfo | grep processor | wc -l`);
    $os       = trim(`cat /etc/os-release | grep -oP 'PRETTY_NAME="\K[^"]+'`);
    $memSize  = trim(`cat /proc/meminfo | grep -oP 'MemTotal:\s+\K\d+'`);
    $memSize  = $memSize / 1024.0 / 1024.0;
    $memSize  = sprintf("%6.2f G", $memSize);

    $diskOut  = trim(`df --output=size,used,avail,pcent -h / | tail -1`);

    $diskCols = preg_split('/[\s]+/', $diskOut);
 
    $disk     = (object)[
      'size'  => $diskCols[0],
      'used'  => $diskCols[1],
      'avail' => $diskCols[2],
      'pcent' => $diskCols[3]
    ];

    /* figure out the RPI model ... */

    $rpiModel = "Unknown";
    $ending   = trim(`cat /proc/cpuinfo | grep 'Revision' | awk '{print $3}' | sed 's/^1000//'`);

    if(isset(self::$revisions[$ending])) {

      $rpiModel = "Raspberry PI ".self::$revisions[$ending][2]." PCB ".self::$revisions[$ending][3]." Memory ".self::$revisions[$ending][4];
    }

    /* build the device info object */

    $obj = (object)[
      'serial' => $serial,
      'model'  => $rpiModel,
      'kind'   => 'rpi',
      'detail' => (object)[
        'cpumodel' => $cpuModel,
        'cpuspeed' => $cpuSpeed,
        'cpucores' => $numCores, 
        'os'       => $os,
        'mem'      => $memSize,
        'disk'     => $disk
      ]
    ];

    /* pass it back */

    return $obj;
  }

}

?>
