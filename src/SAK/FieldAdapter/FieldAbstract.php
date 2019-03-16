<?php
namespace SAK\FieldAdapter;

abstract class FieldAbstract {
    public function getHeaders() {
        return $this->getFields('header');
    }

    public function getSqlFields() {
        return $this->getFields('sql_field');
    }

    public function getIds() {
        $fields = $this->getColumnsAttrs();

        return array_keys($fields);
    }

    public function getHeadersToImport() {
        $fields = $this->getColumnsAttrs();
        $return = [];
        foreach ($fields as $column => $value) {
            if(array_key_exists('to_import', $value) && !$value['to_import']) {
                continue;
            }

            $return[$column] = $value['header'];
        }

        return $return;
    }

    private function getFields($field) {
        $fields = $this->getColumnsAttrs();
        $return = [];
        foreach ($fields as $column => $value) {
            $return[$column] = $value[$field];
        }

        return $return;
    }
}