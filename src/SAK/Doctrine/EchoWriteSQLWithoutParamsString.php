<?php
namespace lsilva\SAK\Doctrine;

/**
 * A SQL logger that logs to the standard output and
 * subtitutes params to get a ready to execute SQL sentence

 * @author  dsamblas@gmail.com
 */
class EchoWriteSQLWithoutParamsString
{
    /**
     * Get SQL from query
     *
     * @author Yosef Kaminskyi
     * @param QueryBilderDql $query
     * @return int
     */
    public static function getFullSQL($query)
    {
        $sql = $query->getSql();
        $paramsList = self::getListParamsByDql($query->getDql());
        $paramsArr =self::getParamsArray($query->getParameters());
        $fullSql='';
        for($i=0;$i<strlen($sql);$i++){
            if($sql[$i]=='?'){
                $nameParam=array_shift($paramsList);

                if(is_string ($paramsArr[$nameParam])){
                    $fullSql.= '"'.addslashes($paramsArr[$nameParam]).'"';
                }
                elseif(is_array($paramsArr[$nameParam])){
                    $sqlArr='';
                    foreach ($paramsArr[$nameParam] as $var){
                        if(!empty($sqlArr))
                            $sqlArr.=',';

                        if(is_string($var)){
                            $sqlArr.='"'.addslashes($var).'"';
                        }else
                            $sqlArr.=$var;
                    }
                    $fullSql.=$sqlArr;
                }elseif(is_object($paramsArr[$nameParam])){
                    switch(get_class($paramsArr[$nameParam])){
                        case 'DateTime':
                                $fullSql.= "'".$paramsArr[$nameParam]->format('Y-m-d H:i:s')."'";
                            break;
                        default:
                            $fullSql.= $paramsArr[$nameParam]->getId();
                    }

                }
                else
                    $fullSql.= $paramsArr[$nameParam];

            }  else {
                $fullSql.=$sql[$i];
            }
        }
        return $fullSql;
    }

    /**
     * Get query params list
     *
     * @author Yosef Kaminskyi <yosefk@spotoption.com>
     * @param  Doctrine\ORM\Query\Parameter $paramObj
     * @return int
     */
    protected static function getParamsArray($paramObj)
    {
        $parameters=array();
        foreach ($paramObj as $val){
            /* @var $val Doctrine\ORM\Query\Parameter */
            $parameters[$val->getName()]=$val->getValue();
        }

        return $parameters;
    }
    public static function getListParamsByDql($dql)
    {
        $parsedDql = preg_split("/:/", $dql);
        $length = count($parsedDql);
        $parmeters = array();
        for($i=1;$i<$length;$i++){
            if(ctype_alpha($parsedDql[$i][0])){
                $param = (preg_split("/[' ' )]/", $parsedDql[$i]));
                $parmeters[] = $param[0];
            }
        }

        return $parmeters;
    }
}
