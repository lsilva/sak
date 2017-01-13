<?php
namespace SAK\Test;

use \GuzzleHttp\Client;

abstract class AbstractApiTestCase extends \PHPUnit_Framework_TestCase {
    private $root_path_api = 'http://localhost';
    private $root_path_test = '/';
    private $response = '';

    public function setRootPathTest($path) {

        $this->root_path_test = $path;
    }
    /**
     * Obtem o corpo da requisição
     * @return String
     */
    protected function getBody() {

        return $this->response->getBody()->read(1024);
    }
    /**
     * Obtem o resultado do método GET
     * @param  array  $params Parametros da request
     * @return @see getBody
     */
    public function getResultGetMethod($params = array()) {
        $client = new Client();
        $this->response = $client->request('GET', $this->root_path_api . $this->root_path_test);

        return $this->getBody();
    }
}