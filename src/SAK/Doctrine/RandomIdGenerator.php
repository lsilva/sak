<?php
namespace SAK\Doctrine;

use Doctrine\ORM\Id\AbstractIdGenerator;

class RandomIdGenerator extends AbstractIdGenerator {
    private $integer_limit = 4294967295;

    public function generate(\Doctrine\ORM\EntityManager $em, $entity) {
        ;
        list($usec, $sec) = explode(" ", microtime());

        $id = substr(round($usec * $sec) . rand(1000, 9999), -10);

        if($id > $this->integer_limit) {
            $id = substr(round($usec * $sec) . rand(1000, 9999), 0, 9);
        }

        return $id;
    }
}
