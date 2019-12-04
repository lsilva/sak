<?php
namespace lsilva\SAK\Doctrine;
/**
* Classe reposnavel por fazer a paginação
*/
class Paginate {
    private $total_itens;
    private $per_page;
    private $page;

    public function getPaginateInfo() {
        $start = $this->per_page * ($this->page - 1);
        $end = $this->per_page * $this->page;
        if ($start < 0) {
            $start = 0;
            $end = $this->per_page;
        }

        if ($end > $this->total_itens) {
            $end = $this->total_itens;
        }

        return [
            'total' => $this->total_itens,
            'per_page' => $this->per_page,
            'page' => $this->page,
            'start' => $start,
            'end' => $end,
        ];
    }

    protected function processPaginationReturn(array $return) {
        if (isset($return['pagination'])) {
            header("Range: {$start} - {$end} / {$return['pagination']['total']}");
            unset($return['pagination']);
        }

        return $return;
    }
    /**
     * Trata a query string para obter o resultado com na pagina correta
     * @param  integer $page     #Pagina que deve ser retornada
     * @param  integer $per_page #Quantidade de itens que devem ser retornados
     * @return \Doctrine\ORM\QueryBuilder\QueryBuilder
     */
    public function getFormattedQuery(\Doctrine\ORM\QueryBuilder $queryBuilder, $page = 1, $per_page = 50) {
        $aSelect = $queryBuilder->getDqlPart('select');
        $selectPart = $aSelect[0]->getParts();

        // Paginação
        // TODO: Criar Cache pela quantidade de registros quanto maior a quantidade maior o tempo de cache
        $count = $queryBuilder->select('count(t) AS total')->getQuery()->getResult();
        if (empty($count[0])) {
            $count[0]['total'] = 0;
        }

        $this->total_itens = (int) $count[0]['total'];
        $this->per_page = $per_page;
        $this->page = $page;

        if (!is_null($this->per_page)) {
            $queryBuilder->setMaxResults($this->per_page);
        }

        if (!is_null($this->page)) {
            $queryBuilder->setFirstResult(($this->page - 1) * $this->per_page);
        }

        $queryBuilder->select($selectPart);

        return $queryBuilder;
    }
}