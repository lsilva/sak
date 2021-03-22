<?php
namespace lsilva\SAK\Doctrine;

use \PHPExcel;
use \PHPExcel\PHPExcel_IOFactory;
use lsilva\SAK\Doctrine\AbstractRepository;
use lsilva\SAK\FieldAdapter\FieldAbstract;

class ExportAdapter {
    private $adapter;
    private $repository;
    private $id_user;
    private $params;
    private $path_download = '/public/download/';
    private $path_upload = '/public/upload/';
    private $objPHPExcel;
    private $current_line = 1;
    private $max_lines_process = 100;
    private $continue_process;
    private $headers = [];

    public function __construct(FieldAbstract $adapter, AbstractRepository $repository) {
        // $this->path_download = realpath(__DIR__.'/../../../../../../../' . $this->path_download) . '/';
        // $this->path_upload = realpath(__DIR__.'/../../../../../../../' . $this->path_upload) . '/';
        $this->adapter = $adapter;
        $this->repository = $repository;
    }

    public function setUserId($id_user) {
        $this->id_user = $id_user;

        return $this;
    }

    public function getUserId($id_user) {
        $this->id_user = $id_user;
    }

    public function getRepository() {
        return $this->repository;
    }

    public function setParams($params) {
        $this->params = $params;
        return $this;
    }

    public function getParams() {
        return $this->params;
    }

    public function setQueryCollection($query) {
        $this->query = $query;

        return $this;
    }

    public function getQueryCollection() {
        return $this->query;
    }

    public function genarateDownloadFile() {
        $status = true;

        $aFactory = [
            'xlsx' => 'Excel2007',
            'xls' => 'Excel5',
            'csv' => 'CSV',
        ];

        // TOODO: Mudar para obter informação do HEADER
        $type = strtolower($this->getParams()->download);
        // TODO: Lançar uma exception
        if (!array_key_exists($type, $aFactory)) {
            return [['name' => $name, 'status' => false]];
        }

        $objPHPExcel = $this->genarateCSVFile();

        $name = 'relatorio_' . date('Ymd') . '_' . rand(1, 1000) . '.' . $type;

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, $aFactory[$type]);
        if ($type === 'csv') {
            $objWriter->setDelimiter(';')
                ->setEnclosure('"')
                ->setSheetIndex(0);
        }

        $objWriter->save($this->getParams()->download_path . $name);

        return [['name' => $name, 'status' => $status]];
    }

    private function genarateCSVFile() {
        $oExcel = new \PHPExcel();
        $oExcel->getProperties()
            ->setCreator("Relatorio Automatico SAK")
            ->setTitle("Relatorio")
            ->setSubject("Relatório gerado automaticamente")
        // ->setDescription("Test document for PHPExcel, generated using PHP classes.")
            ->setKeywords("office PHPExcel php");

        $headersToImport = $this->getRepository()->getFieldAdapter()->getHeadersToImport();
        $fields = array_keys($headersToImport);
        $header = array_values($headersToImport);

        $oExcel = $this->getPHPExcelRow($oExcel, 1, $header);
        $num = 2;
        $result = $this->getQueryCollection()->getQuery()->getResult();
        foreach ($result as $row) {
            $aLine = @$this->getRepository()->getItemsToReturn($row, $fields);

            $line = [];
            foreach ($aLine as $k => $v) {
                $key = array_search($k, $fields);
                if ($key !== false) {
                    $line[$key] = utf8_encode($v);
                }
            }
            ksort($line);
            $oExcel = $this->getPHPExcelRow($oExcel, $num, $line);
            $num++;
        }

        $oExcel->getActiveSheet()->setTitle('Plan1');
        $oExcel->setActiveSheetIndex(0);

        return $oExcel;
    }

    private function getPHPExcelRow(PHPExcel $oExcel, $nLine, $aValues) {
        $col_base = 65; // ASCII 65 = A
        $prefix_col = '';
        $col = 0;

        foreach ($aValues as $value) {
            // Para ter mais de 26 colunas TODO: Prever mais colunas
            if ($col > 0 && $col % 26 === 0) {
                $prefix_col = 'A';
                $col = 0;
            }

            $col_name = $prefix_col . chr($col_base + ($col++)) . $nLine;
            $oExcel->setActiveSheetIndex(0)
                ->setCellValue($col_name, $value);
        }

        return $oExcel;
    }
}