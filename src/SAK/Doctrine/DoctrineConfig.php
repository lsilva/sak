<?php
namespace SAK\Doctrine;

class DoctrineConfig{

	public static function getORMOption($root_path){
		$config = self::getDBOptionStatic($root_path);

		return array(
		    'mappings' => array(
		        array(
		            'type' => 'annotation',
		            'path' => $config['path_entities'],
		            'namespace' => $config['namespace'],
		        ),
		    ),
		);
	}

	public static function getDBOptionDinamic(){

		return array();
		//Fazer um curl para intranet pegando as informacoes de base
	}

	public static function getDBOptionStatic($root_path){
		$file = is_file("{$root_path}/Config/config.php")
			? "{$root_path}/Config/config.php"
			: "{$root_path}/config/config.php";

		require $file;

		return $DBOptionConfig;
	}

   public static function getORMProxyDir($root_path){
		return "{$root_path}/Storage/cache/doctrine/proxies";
	}

	public static function getORMDefaultCache(){
		return 'array';
	}
}