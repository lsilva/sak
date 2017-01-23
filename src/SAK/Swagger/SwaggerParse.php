<?php
namespace SAK\Swagger;

class SwaggerParse {
    private $swagger_file;
    // private $root_namespace_controller = 'Sgc\\Routes\\controllers\\';
    private $root_path_controller = '/Routes/Controllers/';
    private $root_path;

    public function __construct($root_path) {
        $this->root_path = $root_path;
    }

    public static function getDBOptionStatic($root_path){
        $file = is_file("{$root_path}/Config/config.php")
            ? "{$root_path}/Config/config.php"
            : "{$root_path}/config/config.php";

        require $file;

        return $swaggerConfig;
    }

    private function _getFileContentParsed() {
        $swagger_file = "{$this->root_path}/Routes/docs/api-doc.json";
        if(!is_file($swagger_file)) {
            throw new \Exception("File documentation not found: {$swagger_file}", 1);
        }

        $file = file_get_contents($swagger_file);

        return json_decode($file);
    }

    /**
     * Obtem o nome da rota
     * @param  String $name
     * @return String
     */
    private function _getNameRoute($name) {
        $parsed = preg_replace('/\{[^}]*}/','_',$name);
        $parsed = preg_replace('/\//','',$parsed);

        return $parsed;
    }

    /**
     * Obtem o nome do controller
     * Faz o tratamento da saida do método _getNameRoute
     * @param  String $name
     * @return String
     */
    private function _getNameController($name, $route_type) {
        $parsed = preg_replace('/(\_|\-)/',' ',$name);
        $parsed = ucwords($parsed);
        $parsed = preg_replace('/\s/','',$parsed);
        $parsed .= ($route_type ? 'Collection' : '') . 'Controller';

        return $parsed;
    }

    public function getRoutesDefinitions() {
        $oJson = $this->_getFileContentParsed();
        $routes = [];
        $config = self::getDBOptionStatic($this->root_path);

        foreach($oJson->paths as $path_name => $path) {
            $name_route = $this->_getNameRoute($path_name);
            $name_route_key = $name_route;
            // Identifica se trata-se de uma rota do tipo object
            $object_type_route = substr($name_route, -1) === '_';

            if($object_type_route) {
                $name_route = preg_replace('/\_$/','',$name_route);
            }

            $name_controller = $this->_getNameController($name_route, !$object_type_route);

            $result = [];
            $result['pattern'] = $path_name;
            $result['controller'] = $config['namespace_controller'] . '\\' . $this->_getNameController($name_route, !$object_type_route);

            // Cria o arquivo de controller se o mesmo não existir
            $file = "{$this->root_path}{$this->root_path_controller}{$name_controller}.php";
            // var_dump(is_file($file));exit;
            if(!is_file($file)) {
                $this->_createDefaultController($name_controller, $path_name, $file, !$object_type_route);
            }

            $result['methods'] = [];
            foreach($path as $method => $content) {
                $result['methods'][$method] = [];
            }

            $routes[$name_route_key] = $result;
        }

        return $routes;
    }
    /**
     * Cria um arquivo default para o controller
     * @param  String $name_controller
     * @param  String $file_path
     * @return Boolean
     */
    private function _createDefaultController($name_controller, $pattern, $file_path, $route_type) {
        return file_put_contents($file_path, $this->_getDefaultController($name_controller, $pattern, $route_type));
    }
    /**
     * Obtem o arquivo de controller default substrituindo o nome do controller
     * @param  String $name_controller
     * @return String
     */
    private function _getDefaultController($name_controller, $pattern, $route_type) {
        $config = self::getDBOptionStatic($this->root_path);

        $name_template = ($route_type
            ? '/ModelCollectionController.txt'
            : '/ModelObjectController.txt');
        $controller_model = file_get_contents(__DIR__ . $name_template);
        $controller_model = str_replace('NAMECONTROLLER', $name_controller, $controller_model);
        $controller_model = str_replace('NAMESPACE', $config['namespace_controller'], $controller_model);
        $controller_model = str_replace('PATTNER', $pattern, $controller_model);
        return $controller_model;
    }
}