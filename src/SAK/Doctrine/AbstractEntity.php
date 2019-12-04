<?php
namespace lsilva\SAK\Doctrine;

use Doctrine\ORM\Mapping as ORM;

abstract class AbstractEntity {
    /**
     * Método para retornar e setar as propriedades que săo definidas na
     * classe que extende esta classe
     *
     * @param String $methodName
     * @param Array $arguments
     * @return mixed | null
     */
    public function __call($methodName, $arguments) {
        if (preg_match('/^(get|set)(\w+)/', $methodName, $matches)) {
            $propName = lcfirst($matches[2]);
            if (!property_exists($this, $propName)) {
                return;
            }

            if ($matches[1] === 'get') {
                return $this->$propName;
            }

            $this->$propName = $arguments[0];
            return $this;
        }
    }
}