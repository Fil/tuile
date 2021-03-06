<?php

# http://tile.rezo.net/[signature]/[base64:http://www.nnvl.noaa.gov/images/Green/SMNDVI-2012-week25-30000x15000.png]/z/x/y.jpg
# http://tile.rezo.net/[signature]/[base64:http://www.nnvl.noaa.gov/images/Green/SMNDVI-2012-week25-30000x15000.png]/

define ('TILESIZE', 256);

define ('_BIN_CONVERT', 'convert');
define ('_BIN_IDENTIFY', 'identify');

#define ('_BIN_CONVERT', '/opt/local/bin/convert');
#define ('_BIN_IDENTIFY', '/opt/local/bin/identify');

$a = $_SERVER['REQUEST_URI'];

if (preg_match(',tuile/([0-9a-f]+)/([^/]+)/(\d+)/(\d+)/(\d+)$,', $a, $r)) {
	list(, $sig, $b64, $z, $x, $y) = $r;
	$src = @base64_decode($b64);
	$tile = new Tile($z, $x, $y, $sig, $src);
	$tile->display();
} else
if (preg_match(',tuile/([0-9a-f]+)/([^/]+)(/|\.json)$,', $a, $r)) {
	list(, $sig, $b64, $mode) = $r;
	$src = @base64_decode($b64);
	$tile = new TileMode($sig, $src);

	$mode = ($mode == '/' ? '' : 'json');
	$tile->display($mode);
} else {
	error(404);
}


function error($code) {
	echo "Erreur $code";
	exit;
}


class TileMode {
	var $src;
	var $sig;
	var $local;
	private $dir;

	function TileMode($sig, $src) {
		$this->src = $src;
		$this->sig = $sig;
		$this->local = 'local/'.rawurlencode($this->src);
		$this->dir = 'cache/'. rawurlencode($this->src) . '/' ;
		if (!file_exists($this->local)) {
			$this->load();
		}
		if (!filesize($this->local)) {
			error (404);
		}
	}

	function load() {
		mkdir (dirname($this->local), 0777, true);
		touch ($this->local);

		if ($i = file_get_contents($this->src)) {
			($fp = fopen($this->local . '.tmp', 'w'))
			&& fwrite($fp, $i)
			&& fclose($fp)
			&& rename($this->local . '.tmp', $this->local);
		} else {
			die ("BOUH PAS CHARGE");
		}
	}

	function display($mode=null) {
		if ($mode == 'json') {
			header('Content-Type: text/plain; charset=utf-8');
			echo json_encode($this, JSON_PRETTY_PRINT);
			exit;
		}
		
		if (!$leaflet = @file_get_contents($f = $this->dir.'/index.html')) {
			$mpc = $this->local;
			$qmpc = escapeshellarg($mpc);
			$ident = exec(_BIN_IDENTIFY." ".$qmpc);
			if (preg_match('/ (\d+)x(\d+) /', $ident, $r)) {
				$dim = array_map('intval',array($r[2], $r[1]));
			}
			elseif (!$dim = getimagesize($mpc))
				die ("source '$mpc'?");
			$leaflet = file_get_contents('template/leaflet.html');
			$leaflet = str_replace('#OPUS', $this->sig, $leaflet);
			$leaflet = str_replace('#WIDTH', $dim[0], $leaflet);
			$leaflet = str_replace('#HEIGHT', $dim[1], $leaflet);
			$leaflet = str_replace('#SOURCE', $this->local, $leaflet);


			($fp = @fopen($f, 'w'))
			&& @fwrite($fp, $leaflet)
			&&@fclose($fp);
		}
		header('Content-Type: text/html; charset=utf-8');
		echo $leaflet;
		exit;
	}
}

class Tile {

	var $secret = '';
	var $z, $x, $y, $sig, $src;
	private $dir;
	private $cache;
	private $db;
	private $tile;
	var $local;

	function Tile($z, $x, $y, $sig, $src) {
		$this->z = $z;
		$this->x = $x;
		$this->y = $y;
		$this->src = $src;
		$this->sig = $sig;
		$this->local = 'local/'.rawurlencode($this->src);

		$this->dir = 'cache/'. rawurlencode($this->src) . '/' ;
		$this->cache = $this->dir . ($this->z) . '/' . ($this->x) . '/' . ($this->y) . '.jpg';

	#	$secret = 'aaa';
	#	if ($sig != md5($secret . $src)) error (401);

	}

	function display() {
		if (!$this->exists()) {
			$this->create();
		}
		$this->send();
	}

	function exists() {
		// src/z,x,y is a mbtiles.
		if (@file_exists($mbtiles = '../IMG/mbtiles/' . $this->src . '.mbtiles')) {
			try {
				$this->db = new PDO('sqlite:' . $mbtiles, '', '', array(PDO::ATTR_PERSISTENT => true));
			} catch (Exception $exc) {
				echo $exc->getTraceAsString();
				die;
			}
			// flip
			$z = floatval($this->z);
			$y = floatval($this->y);
			$x = floatval($this->x);
			                  
			$y = pow(2, $z) - 1 - $y;
			$result = $this->db->query('select tile_data as t from tiles where zoom_level=' . $z . ' and tile_column=' . $x . ' and tile_row=' . $y);
			return $this->tile = ($result ? $result->fetchColumn() : false);
		}
		else
			return @file_exists($this->cache);
	}

	function create() {
		if (@file_exists($this->dir . $this->z . '/index.html')) {
			error (503); // attends, pas fini
		}

		else
		{
		
			create_level($this->local,$this->z, $this->dir);
		}

		if ($this->exists())
			$this->send();
	}

	function send() {
		if (!$this->exists()) {
			error (404);
		}
		$mbtiles = '../IMG/mbtiles/' . $this->src . '.mbtiles';
		if ($this->tile) {
			// JPEG magic number = FFD8
			$magic = substr($this->tile, 0,2) == chr(255) . chr(216)
				? 'jpeg'
				: 'png';
			header('Content-Type: image/' . $magic);
			echo $this->tile;
		} else {
			header('Content-Type: image/jpeg');
			@readfile($this->cache);
		}
		exit;
	}

}



function create_level($mpc, $z, $dest) {
	mkdir ($dest.$z, 0777, true);
	touch ($dest.$z.'/index.html');

	$qmpc = escapeshellarg($mpc);
	$c = _BIN_IDENTIFY . ' ' . $qmpc;
	$ident = `$c`;
	if (preg_match('/ (\d+)x(\d+) /', $ident, $r)) {
		$dim = array_map('intval',array($r[2], $r[1]));
	}
	elseif (!$dim = getimagesize($mpc))
		die ('source2?');

	$h = $dim[0]; $w=$dim[1];

	$zmax = ceil(log(max($h,$w) / TILESIZE, 2));


	$factor = 100*(pow(2,$z-$zmax))."%";
	$c = _BIN_CONVERT." ".escapeshellarg($mpc."[$factor]")
		." -crop ".TILESIZE."x".TILESIZE
		." -set filename:tile \"".$z."-%[fx:page.x/".TILESIZE."]-%[fx:page.y/".TILESIZE."]\""
		." +repage +adjoin \"%[filename:tile].jpg\""
	;

	echo $c;
	$a = exec ($c, $output, $return_var);

	# redispatcher le niveau dans $dest/z/y/x.jpg
	# et remplir les tiles incompletes
	# (alternativement, employer "mbutil" pour faire sqlite/mbtiles)
	foreach(glob("$z-*-*.jpg") as $i) {
		if (preg_match(",^\d+-\d+-\d+\.jpg$,S", $i)) {
			$j = $dest.str_replace("-", "/", $i);
			@mkdir(dirname($j), 0777, true);
			$c = _BIN_CONVERT." -extent ".TILESIZE."x".TILESIZE." -strip ".escapeshellarg($i)." ".escapeshellarg($j);
			echo "$c\n";
			shell_exec($c);
			unlink($i);
		}
	}

	#echo "\ndone\n";

}

