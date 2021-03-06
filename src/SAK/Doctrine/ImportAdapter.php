<?php
namespace lsilva\SAK\Doctrine;


class ImportAdapter {
    private $adapter;
    private $params;
    private $path_download = '/public/download/';
    private $path_upload = '/public/upload/';
    private $objPHPExcel;
    private $current_line = 1;
    private $max_lines_process = 100;
    private $continue_process;
    private $headers = [];

    public function __construct(\lsilva\SAK\FieldAdapter\FieldAbstract $adapter) {
        $this->adapter = $adapter;
    }

    public function getAdapter() {
        return $this->adapter;
    }

    public function setParams($params) {
        $this->params = $params;
        return $this;
    }

    public function getContinueProcessStatus() {
        return $this->continue_process;
    }

    public function importFile($fileToImport) {
        try {
            $inputFileType = \PHPExcel_IOFactory::identify($fileToImport);
            $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
            $this->objPHPExcel = $objReader->load($fileToImport);
        }
        catch (\Exception $e) {
            die('Error loading file "' . pathinfo($fileToImport, PATHINFO_BASENAME)
            . '": ' . $e->getMessage());
        }

        return $this->process();
    }

    public function process() {
        //  Get worksheet dimensions
        $sheet = $this->objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $aLines = [];

        $this->continue_process = false;
        //  Loop through each row of the worksheet in turn
        for ($row = $this->current_line, $count = 1; $row <= $highestRow; $row++, $count++) {
            $oNewLine = new \Stdclass;
            //  Read a row of data into an array
            $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);

            $first_line = empty($this->headers);
            foreach($rowData[0] as $k => $v) {
                if($first_line) {
                    $this->headers[] = $v;
                }
                else {
                    $oNewLine->{$this->headers[$k]} = $v;
                }
            }

            if($first_line) {
                continue;
            }

            $aLines[] = $oNewLine;
            if(($count % $this->max_lines_process) === 0) {
                $this->continue_process = true;
                break;
            }
        }

        $this->current_line = ++$row;

        return $aLines;
    }
}
