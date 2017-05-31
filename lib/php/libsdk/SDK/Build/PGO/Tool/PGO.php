<?php

namespace SDK\Build\PGO\Tool;

use SDK\{Config as SDKConfig, Exception};
use SDK\Build\PGO\Config as PGOConfig;
use SDK\Build\PGO\Interfaces;

class PGO
{
	protected $php;
	protected $conf;
	protected $idx = 0;

	public function __construct(PGOConfig $conf, Interfaces\PHP $php)
	{
		$this->conf = $conf;
		$this->php = $php;		
	}

	protected function getPgcName(string $fname) : string
	{
		$bn = basename($fname, substr($fname, -4, 4));
		$dn = dirname($fname);

		return $dn . DIRECTORY_SEPARATOR . $bn . "!" . $this->idx . ".pgc";
	}

	protected function getPgdName(string $fname) : string
	{
		$bn = basename($fname, substr($fname, -4, 4));
		$dn = dirname($fname);

		return $dn . DIRECTORY_SEPARATOR . $bn . ".pgd";
	}

	protected function getWorkItems() : array
	{
		$exe = glob($this->php->getRootDir() . DIRECTORY_SEPARATOR . "*.exe");
		$dll = glob($this->php->getRootDir() . DIRECTORY_SEPARATOR . "*.dll");
		$dll = array_merge($dll, glob($this->php->getExtRootDir() . DIRECTORY_SEPARATOR . "php*.dll"));

		/* find out next index */
		$tpl = glob($this->php->getRootDir() . DIRECTORY_SEPARATOR . "php7{ts,}.dll", GLOB_BRACE)[0];
		if (!$tpl) {
			throw new Exception("Couldn't find php7[ts].dll in the PHP root dir.");
		}
		do {
			if (!file_exists($this->getPgcName($tpl))) {
				break;
			}
			$this->idx++;
		} while (true);

		return array_unique(array_merge($exe, $dll));
	}

	public function dump(bool $merge = true) : void
	{
		$its = $this->getWorkItems();	

		foreach ($its as $base) {
			$pgc = $this->getPgcName($base);
			$pgd = $this->getPgdName($base);

			`pgosweep $base $pgc`;
			//passthru("pgosweep $base $pgc");

			if ($merge) {
				`pgomgr /merge:1000 $pgc $pgd`;
				//passthru("pgomgr /merge:1000 $pgc $pgd");
			}
		}
	}

	public function waste() : void
	{
		$this->dump(false);
	}

	public function clean() : void
	{
		$its = glob($this->php->getRootDir() . DIRECTORY_SEPARATOR . "*.pgc");
		$its = array_merge($its, glob($this->php->getExtRootDir() . DIRECTORY_SEPARATOR . "*" . DIRECTORY_SEPARATOR . "*.pgc"));
		foreach (array_unique($its) as $pgc) {
			unlink($pgc);
		}

		$its = glob($this->php->getRootDir() . DIRECTORY_SEPARATOR . "*.pgd");
		$its = array_merge($its, glob($this->php->getExtRootDir() . DIRECTORY_SEPARATOR . "*" . DIRECTORY_SEPARATOR . "*.pgd"));
		foreach (array_unique($its) as $pgd) {
			`pgomgr /clear $pgd`;
			//passthru("pgomgr /clear $pgd");
		}
	}
}

