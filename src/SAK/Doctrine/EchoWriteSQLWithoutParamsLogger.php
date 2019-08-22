<?php
namespace SAK\Doctrine;

use Doctrine\DBAL\Logging\SQLLogger,
    Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Platforms\AbstractPlatform;
/**
 * A SQL logger that logs to the standard output and
 * subtitutes params to get a ready to execute SQL sentence

 * @author  dsamblas@gmail.com
 */
class EchoWriteSQLWithoutParamsLogger implements SQLLogger

{
    const QUERY_TYPE_SELECT="SELECT";
    const QUERY_TYPE_UPDATE="UPDATE";
    const QUERY_TYPE_INSERT="INSERT";
    const QUERY_TYPE_DELETE="DELETE";
    const QUERY_TYPE_CREATE="CREATE";
    const QUERY_TYPE_ALTER="ALTER";

    private $dbPlatform;
    private $loggedQueryTypes;
    public function __construct(AbstractPlatform $dbPlatform, array $loggedQueryTypes=array()){
        $this->dbPlatform=$dbPlatform;
        $this->loggedQueryTypes=$loggedQueryTypes;
    }
    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)

    {
        // if($this->isLoggable($sql)){
            if(!empty($params)){
                foreach ($params as $key=>$param) {
                    $type=Type::getType($types[$key]);
                    $value=$type->convertToDatabaseValue($param,$this->dbPlatform);
                    $sql = join(var_export($value, true), explode('?', $sql, 2));
                }

            }
            echo $sql . " ;".PHP_EOL;
        // }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {

    }
    private function isLoggable($sql){
        if (empty($this->loggedQueryTypes)) return true;
        foreach($this->loggedQueryTypes as $validType){
            if (strpos($sql, $validType) === 0) return true;
        }
        return false;
    }
}