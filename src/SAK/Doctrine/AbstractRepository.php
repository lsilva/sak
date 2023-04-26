<?php
namespace lsilva\SAK\Doctrine;

abstract class AbstractRepository extends \Doctrine\ORM\EntityRepository {
    public $objPaginate;

    protected $debbug_query_collection = false;

    protected $fieldAdapter = null;

    protected $entityClass = null;

    protected $path_fieldadapter_default = '%s\Library\FieldAdapter\%sAdapter';

    protected $path_entity_default = '%s\Entity\%s';

    protected function getFileNameReturn($pathDownload) {
        $file_name_return = 'retorno_upload_' . date('YmdHi') . '_' . rand(1, 1000) . '.csv';
        $file_return = $pathDownload . $file_name_return;
        return [ 'file_name_return' => $file_name_return, 'file_return' => $file_return ];
    }

    public function importFromFile($fileToImport, $pathDownload) {
        list($file_name_return, $file_return) = array_values($this->getFileNameReturn($pathDownload));
        $oReturnFile = fopen($file_return, 'w');

        $adapter = new ImportAdapter($this->getFieldAdapter());

        $rows = $adapter->importFile($fileToImport);
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
                $cloneObj = clone $this;
                $aCreate = $cloneObj->prepareLineToImport($row);

                $message = $aCreate['message'];
                if ($aCreate['status']) {
                    $cloneObj->create($aCreate['object']);
                    $message = 'SUCESSO';
                }
            } catch (\Exception $e) {
                $message = $e->getMessage();

                /**
                 * @see
                 * Quando ocorre um erro no insert é exibido um erro 'The EntityManager is closed Doctrine'
                 * as linhas abaixo reabrem a conexão quando evitando esse erro
                 */
                if (!$this->_em->isOpen()) {
                    $this->_em = $this->_em->create(
                        $this->_em->getConnection(),
                        $this->_em->getConfiguration()
                    );
                }
            } finally {
                $row->message = $message;
                fputcsv($fileReturn, (array) $row);
            }
        }

        return true;
    }

    public function getPaginateInfo() {
        return $this->objPaginate ? $this->objPaginate->getPaginateInfo() : null;
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
    public function getFieldAdapter() {
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
    public function fetchCollection($params = [], $filters = [], $orders = [], $page = 1, $limit = 100) {
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

		if ($params && !is_null($this->getAttr($params, 'download'))) {
			$adapter = new ExportAdapter($this->getFieldAdapter(), $this);

			$adapter->setParams($params)->setQueryCollection($collection);

			return $adapter->genarateDownloadFile();
        }

        $this->objPaginate = new Paginate();
        $collection = $this->objPaginate->getFormattedQuery($collection, $page, $limit);

        $collection->setMaxResults($limit);

        $collection->setFirstResult(($page - 1) * $limit);

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
    public function getItemsToReturn(array $row, array $fields) {
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
     * Realiza a atualização do objeto ou insere um novo
     * @param  Array  $id      # Id do registro que deve ser
     * @param  Array  $arrValues    # Array dos novos valores
     * @return Array
     */
	public function updateOrCreate($id, $arrValues) {
		if (empty($id)) {
			$ret = $this->create($arrValues);
		} else {
			$ret = $this->update($id, $arrValues);
		}

		return $ret;
	}

    /**
     * Obtem o valor do campo que devera ser gravado.
     * @param String    $fieldName      # Nome do campo
     * @param Array     $arrValues      # Array dos novos valores
     * @param Array     $arrOldValues   # Array dos valores antigos se for uma atualização
     * @param Array     $defaultValue   # Valor default para a coluna caso seja um novo registro
     */
    protected function getValueToEntity(string $fieldName, array $arrValues, array $arrOldValues = null, $defaultValue = null) {
        $value = null;
        if (array_key_exists($fieldName, $arrValues)) {
            $value = $arrValues[$fieldName];
        } elseif ($arrOldValues && array_key_exists($fieldName, $arrOldValues)) {
            $value = $arrOldValues[$fieldName];
        } elseif (is_null($arrOldValues) && !is_null($defaultValue)) {
            $value = $defaultValue;
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
    public function update($id, $data) {
        $oldEntity = $this->fetch($id);

        if (!empty($oldEntity)) {
            $this->_em->getConnection()->beginTransaction();
            try {
                $entity = $this->setItemsToEntity($data, null, $oldEntity);
                $entity->setId($id);

                $this->_em->merge($entity);
                $this->_em->flush();

                $this->postUpdate($entity, $data);

                $this->_em->getConnection()->commit();
            } catch (\Exception $e) {
                $this->_em->getConnection()->rollback();

                throw new \Exception($e->getMessage(), ($e->getCode() ? $e->getCode() : 400));
            }
        }
        else {
            throw new \Exception('Registro nao encontrado '.$id, 400);
        }

        return $this->fetch($entity->getId());
    }

    /**
     * Cria um objeto
     * @param  Array     $data    # Conteúdo do objeto que será guardado
     * @return StdClass           # Objeto que foi persistido
     */
    public function create($data) {
        $this->validParams($data);

        $this->_em->getConnection()->beginTransaction();
        try {
            $entity = $this->setItemsToEntity($data, $this->getEntity());

            $this->_em->persist($entity);
            $this->_em->flush();

            $this->postInsert($entity->getId(), $data);

            $this->_em->getConnection()->commit();
        } catch (\Exception $e) {
            $this->_em->getConnection()->rollback();
            // Se for erro de SQL trata para a mensagem não ficar muito grande
            $message = $e->getMessage();
            if(!empty(preg_match('/SQLSTATE(.*)/', $message, $matches))) {
                $message = $matches[0];
            }

            throw new \Exception($message, ($e->getCode() ? $e->getCode() : 400));
        }

        return $this->fetch($entity->getId());
    }

    /**
     * Obtem um registro
     * @param $id
     * @return StdClass
     */
    public function fetch($id) {
        return $this->findOneById([
            'id' => $id,
        ]);
    }

    /**
     * postInsert
     * Método executado após obter o id da entidade que sera inserida
     *
     * @param  integer $id
     * @param  mixed $data      # Valores passados para inserção
     * @return void
     */
    public function postInsert($id, $data) {}

    /**
     * postUpdate
     * Método executado após obter o id da entidade que sera atualizada
     *
     * @param  integer $id
     * @param  mixed $data      # Valores passados para inserção
     * @return void
     */
    public function postUpdate($entity, $data) {}

    /**
     * setItemsToEntity
     *
     * Preenche a entidade com os valores passados
     *
     * @param  mixed $aValues
     * @param  mixed $entity
     * @param  mixed $oldEntity
     * @return Object
     */
    protected function setItemsToEntity($aValues, $entity = null, $oldEntity = null) {}

    /**
     * validParams
     * Validação dos parametros passados
     *
     * @param  Array     $params    # Conteúdo do objeto que será guardado
     * @return Boolean
     */
    protected function validParams($params) {
        // TODO: Obter esses campos a partir da documentação
        $requiredFields = [];

        $diff = array_diff($requiredFields, array_keys($params));
        if (!empty($diff)) {
            throw new \Exception("Os seguintes campos são obrigatórios: " . implode(', ', $diff), 1);
        }

        return true;
    }

    protected function getAttr($class, $attr) {
        $value = null;
        if ($class && property_exists($class, $attr)) {
            $value = $class->{$attr};
        }

        return $value;
    }
}
