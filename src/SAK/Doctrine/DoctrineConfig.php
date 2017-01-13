<?php
namespace SAK\Doctrine;

class DoctrineConfig{

	public static function getORMOption(){

		return array(
		    'mappings' => array(
		        array(
		            'type' => 'annotation',
		            'path' => $this->getDBOptionStatic()['path_entities'],
		            'namespace' => $this->getDBOptionStatic()['namespace'],
		        ),
		    ),
		);
	}

	public static function getDBOptionDinamic(){

		return array();
		//Fazer um curl para intranet pegando as informacoes de base
	}

	public static function getDBOptionStatic(){
		require_once __DIR__."/../../Config/config.php";
		return $DBOptionConfig;
	}

   public static function getORMProxyDir(){

		return __DIR__.'/../../Storage/cache/doctrine/proxies';
	}

	public static function getORMDefaultCache(){
		return 'array';
	}
}