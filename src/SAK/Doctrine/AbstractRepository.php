<?php
namespace SAK\Doctrine;

abstract class AbstractRepository extends \Doctrine\ORM\EntityRepository {
    public $objPaginate;

    protected $debbug_query_collection = false;

    protected $fieldAdapter = null;

    protected $entityClass = null;

    protected $path_fieldadapter_default = '%s\Library\FieldAdapter\%sAdapter';

    protected $path_entity_default = '%s\Entity\%s';

    protected function getFileNameReturn() {
		$file_name_return = 'retorno_upload_' . date('Ymd') . '_' . rand(1, 1000) . '.csv';
		$file_return = realpath('.') . $this->path_download . $file_name_return;
		return [ 'file_name_return' => $file_name_return, 'file_return' => $file_return ];
	}

	public function importFromFile($params) {
		// $file_name_return = 'retorno_upload_' . date('Ymd') . '_' . rand(1, 1000) . '.csv';
		// $file_return = realpath('.') . $this->path_download . $file_name_return;
		list($file_name_return, $file_return) = array_values($this->getFileNameReturn());
		$oReturnFile = fopen($file_return, 'w');

		$adapter = new ImportAdapter($this->getFileManagerAdapter());

		$rows = $adapter->setParams($params)->importFile();
		$this->importInsertLines($rows, $oReturnFile);
		$status = $adapter->getContinueProcessStatus();
		while($status) {
   		    $rows = $adapter->process();
		    $this->importInsertLines($rows, $oReturnFile);
		    $status = $adapter->getContinueProcessStatus();
		}

	    fclose($oReturnFile);

	    return ['file_name' => $file_name_return];
	}

	public function importInsertLines($rows, $fileReturn) {
		foreach($rows as $row) {
	        try {
	        	// Prepara o objeto que deverá ser inserido e faz algumas validações basicas
	        	$aCreate = $this->prepareLineToImport($row);

	        	// var_dump($row, $aCreate);exit;

	        	$message = $aCreate['message'];
	        	if ($aCreate['status']) {
	        		$this->create($aCreate['object']);
	        		$message = 'SUCESSO';
	        	}
	        } catch (\Exception $e) {
	        	$message = $e->getMessage();
	        } finally {
	        	$row->message = $message;
	        	fputcsv($fileReturn, (array) $row);
	        }
		}

	    return true;
	}

    public function getPaginateInfo() {
        return $this->objPaginate->getPaginateInfo();
    }

    /**
     * Obtem um item da coleção
     * @param  Array $aKeys  # Keys que devem ser utilizadas na busca
     * @return StdClass      # Item da coleção
     */
    protected function findOneById($aKeys) {
        $collection = $this->getQueryToFeatchCollection();

        $collection = $this->getFilters($collection, $aKeys);

        // Debuugar a query
        if($this->debbug_query_collection) {
            echo ($collection->select($selectPart)->getQuery()->getSql());
            exit;
        }

        $fields = $this->getAllHeadersToDisplay();
        $result = $collection->getQuery()->getOneOrNullResult();

        $data = [];
        if (!empty($result)) {
            $data = $this->getItemsToReturn($result, $fields);
        }

        return $data;
    }
    /**
     * Get Enity instance
     * @return \Api\Entity\CampanhaConveniadaVacina
     */
    protected function getEntity() {
        if(is_null($this->entityClass)) {
            if(preg_match('/(.*)\\\\Repository\\\\(.*)Repository$/', get_class($this), $matches)) {
                $className = sprintf($this->path_entity_default, $matches[1], $matches[2]);
                $this->entityClass = new $className;
            }
            else {
                throw new \Exception("Path entity not found", 500);

            }
        }

        return $this->entityClass;
    }

    /**
     * Obtem o nome do adaptador que será utilizado como interface do banco
     * @return Object::SAK\FieldAdapter\FieldAbstract
     */
    protected function getFieldAdapter() {
        if(is_null($this->fieldAdapter)) {
            if(preg_match('/(.*)\\\\Repository\\\\(.*)Repository$/', get_class($this), $matches)) {
                $className = sprintf($this->path_fieldadapter_default, $matches[1], $matches[2]);
                $this->fieldAdapter = new $className;
            }
        }

        return $this->fieldAdapter;
    }

    /**
     * Obtem a coleção que deve ser retornada
     * @param  Array  $params   # Parametros que serão tratados para obter o resultado
     * @return Array
     */
    public function fetchCollection($params = [], $filters = [], $orders = []) {
        $data = [];
        // Obtem a query que deve ser executada
        $collection = $this->getQueryToFeatchCollection();

        // Obtem os filtros que devem ser aplicados, considerando os parametros passados
        $collection = $this->getFilters($collection, $filters);

        // Obtem a ordenação que será aplicada, considerando os parametros passados
        $collection = $this->getOrders($collection, $orders);

        // Debuugar a query
        if($this->debbug_query_collection) {
            echo ($collection->select($selectPart)->getQuery()->getSql());
            exit;
        }

        $this->objPaginate = new Paginate();
        $collection = $this->objPaginate->getFormattedQuery($collection);

        $result = $collection->getQuery()->getScalarResult();

        $fields = $this->getAllHeadersToDisplay();

        if (!empty($result)) {
            foreach ($result as $row) {
                $data[] = $this->getItemsToReturn($row, $fields);
            }
        }

        return $data;
    }

    /**
     * Obtem o item que será retornado aplicando alguns filtros padrões
     * @param  Array  $row     # Conteudo que será retornado
     * @param  Array  $fields  # Campos permitidos para serem retornados
     * @return Array
     */
    protected function getItemsToReturn(array $row, array $fields) {
        $rowReturn = $row;

        foreach ($row as $k => $v) {
            if (!in_array($k, $fields)) {
                unset($rowReturn[$k]);
            }

            if ($rowReturn[$k] instanceof \DateTime) {
                $rowReturn[$k] = $rowReturn[$k]->format('Y-m-d H:i:s');
            }

            if ($rowReturn[$k] === null || strtolower($rowReturn[$k]) === 'null') {
                $rowReturn[$k] = '';
            }
        }

        return $rowReturn;
    }

    /**
     * Obtem o valor do campo que devera ser gravado.
     * @param String    $fieldName      # Nome do campo
     * @param Array     $arrValues      # Array dos novos valores
     * @param Array     $arrOldValues   # Array dos valores antigos se for uma atualização
     */
    protected function getValueToEntity(string $fieldName, array $arrValues, array $arrOldValues = null) {
        $value = null;
        if (array_key_exists($fieldName, $arrValues)) {
            $value = $arrValues[$fieldName];
        } elseif ($arrOldValues && array_key_exists($fieldName, $arrOldValues)) {
            $value = $arrOldValues[$fieldName];
        }

        return $value;
    }

    /**
     * Reponsável por montar a clausula orderBy da collection
     * @param \Doctrine\ORM\QueryBuilder $collection  # Query que será atualizada
     * @param Array                      $orders      # Array conténdo os campos que se deseja ordenar
     * @param Array                      $keysAllowed # Campos prédeterminados para ordenação
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function addOrderBy($collection, $orders, $keysAllowed) {
        foreach ($orders as $order => $fields) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $keysAllowed)) {
                    $collection->addOrderBy($keysAllowed[$field], $order);
                    // não permetirá entrar novamente aqui se a chave for repetida
                    unset($keysAllowed[$field]);
                }
            }
        }

        return $collection;
    }
    /**
     * Obtem a Ordenação do query builder, esse metodo poderá ser reescrito
     * pela classe que esta extendendo essa para retornar a ordenação correta
     * @param  \Doctrine\ORM\QueryBuilder $collection # Query Builder a ser tratado
     * @return \Doctrine\ORM\QueryBuilder             # Com a inclusão dos orders
     */
    abstract protected function getOrders($collection, $params);

    /**
     * Obtem os filtros que devem ser aplicados na consulta levando em consideração
     * os parametros que são passados na URL
     * @param  \Doctrine\ORM\QueryBuilder $collection # Query Builder a ser tratado
     * @param  Array $params                          # Conteúdo da query
     * @return \Doctrine\ORM\QueryBuilder             # Com a inclusão dos filters
     */
    abstract protected function getFilters($collection, $params);

    /**
     * Atualiza um objeto
     * @param  Integer $id   # ID do objeto que será persistido, se a classe tiver mais de um
     *                         identificador o método precisará ser sobrescrito
     * @param  Array   $data # Conteúdo que se deseja atualizar
     * @return StdClass      # Objeto que foi atualizado
     */
    abstract public function update($id, $data);

    /**
     * Cria um objeto
     * @param  Array     $data    # Conteúdo do objeto que será guardado
     * @return StdClass           # Objeto que foi persistido
     */
    abstract public function create($data);

    /**
     * Obtem um registro
     * @param $id
     * @return StdClass
     */
    abstract public function fetch($id);

    protected function getAttr($class, $attr) {
        $value = null;
        if (property_exists($class, $attr)) {
            $value = $class->{$attr};
        }

        return $value;
    }
}
