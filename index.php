<?php

define ('TILESIZE', 256);
define ('_BIN_CONVERT', '/opt/local/bin/convert');
define ('_BIN_IDENTIFY', '/opt/local/bin/identify');

$a = $_SERVER['REQUEST_URI'];

if (preg_match(',tuile/([0-9a-f]+)/(\d+)/(\d+)/(\d+)/(.*)$,', $a, $r)) {
	list(, $sig, $z, $x, $y, $src) = $r;
	$tile = new Tile($z, $x, $y, $sig, $src);
	$tile->display();
} else
if (preg_match(',tuile/([0-9a-f]+)/(leaflet)/(.*)$,', $a, $r)) {
	list(, $sig, $mode, $src) = $r;
	$tile = new TileMode($sig, $mode, $src);
	$tile->display();
} else {
	error(404);
}


function error($code) {
	echo "Erreur $code";
	exit;
}


class TileMode {
	var $mode;
	var $src;
	var $sig;
	var $local;
	function TileMode($sig, $mode, $src) {
		$this->mode = $mode;
		$this->src = $src;
		$this->sig = $sig;
		$this->local = 'local/'.md5($this->src);

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

	function display() {
		if ($this->mode == 'leaflet') {
			$qmpc = escapeshellarg($this->local);
			$ident = exec(_BIN_IDENTIFY." ".$qmpc);
			if (preg_match('/ (\d+)x(\d+) /', $ident, $r)) {
				$dim = array_map('intval',array($r[2], $r[1]));
			}
			elseif (!$dim = getimagesize($mpc))
				die ('source?');
			$leaflet = file_get_contents('modeles/leaflet.html');
			$leaflet = str_replace('#OPUS', $this->sig, $leaflet);
			$leaflet = str_replace('#WIDTH', $dim[0], $leaflet);
			$leaflet = str_replace('#HEIGHT', $dim[1], $leaflet);
			$leaflet = str_replace('#SOURCE', $this->local, $leaflet);
			header('Content-Type: text/html');
			echo $leaflet;
			exit;
		}
	}
}

class Tile {

	var $secret = '';
	var $z, $x, $y, $sig, $src;
	private $dir;
	private $cache;

	function Tile($z, $x, $y, $sig, $src) {
		$this->z = $z;
		$this->x = $x;
		$this->y = $y;
		$this->src = $src;
		$this->sig = $sig;

		$this->dir = 'cache/'. ($this->sig) . '/' ;
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
		return @file_exists($this->cache);
	}

	function create() {
		if (@file_exists($this->dir . $this->z . '/index.html')) {
			error (503); // attends, pas fini
		}

		else
		{
			create_level($this->src,$this->z, $this->dir);
		}

		if ($this->exists())
			$this->send();
	}

	function send() {
		if (!$this->exists()) {
			error (404);
		}
		header('Content-Type: image/jpeg');
		@readfile($this->cache);
		exit;
	}

}



function create_level($mpc, $z, $dest) {
	mkdir ($dest.$z, 0777, true);
	touch ($dest.$z.'/index.html');

	$qmpc = escapeshellarg($mpc);
	$ident = `/opt/local/bin/identify $qmpc`;
	if (preg_match('/ (\d+)x(\d+) /', $ident, $r)) {
		$dim = array_map('intval',array($r[2], $r[1]));
	}
	elseif (!$dim = getimagesize($mpc))
		die ('source?');

	$h = $dim[0]; $w=$dim[1];

	$zmax = ceil(log(max($h,$w) / TILESIZE, 2));


	$factor = 100*(pow(2,$z-$zmax))."%";
	$c = _BIN_CONVERT." ".escapeshellarg($mpc."[$factor]")
		." -crop ".TILESIZE."x".TILESIZE
		." -set filename:tile \"".$z."-%[fx:page.x/".TILESIZE."]-%[fx:page.y/".TILESIZE."]\""
		." +repage +adjoin \"%[filename:tile].jpg\""
	;

	$a = exec ($c, $output, $return_var);

	# redispatcher le niveau dans $dest/z/y/x.jpg
	# et remplir les tiles incompletes
	# (alternativement, employer "mbutil" pour faire sqlite/mbtiles)
	foreach(glob("$z-*-*.jpg") as $i) {
		if (preg_match(",^\d+-\d+-\d+\.jpg$,S", $i)) {
			$j = $dest.str_replace("-", "/", $i);
			@mkdir(dirname($j), 0777, true);
			$c = "/opt/local/bin/convert -extent ".TILESIZE."x".TILESIZE." -strip ".escapeshellarg($i)." ".escapeshellarg($j);
			#echo "$c\n";
			shell_exec($c);
			unlink($i);
		}
	}

	echo "\ndone\n";

}

