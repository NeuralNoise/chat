<?php

class Console {

    public static function start(){
        self::write("Console - start");
    }

    public static function write($string){

        global $config;
        $file = fopen($config['server']['logfile'],"a");

        $memoryUsage = '';
        if( function_exists('memory_get_usage') ) {
            $memoryUsage = "Mem: ".memory_get_usage()." bytes ";
        }

        fputs ($file, "[<b>".date("Y.m.d-H:i:s")."</b>] ". $memoryUsage . $string ."<br />\r\n");
        fclose($file);
    }

    public static function end(){
        self::write("Console - end");
    }

    public static function format($variable){

        if (!isset($variable) ) {
            return "undefined";
        }

        $res="";
        if (is_null($variable) ){ //Если NULL
            $res.="NULL";
        }
        elseif (is_callable($variable)){
            $res.= "<b>callable</b> ";
            if (is_string($variable)){
                $functions = get_defined_functions();
                $res.= array_search($variable, $functions['internal'])? 'internal':'user';
                $res.= ' function '.htmlspecialchars($variable).'()';
            }
            elseif (is_array($variable)){
                reset($variable);
                $class  = current($variable);
                $method = next($variable);
                if (is_string($class))      {  $res.= 'static class '.$class.'::'.$method.'()';				 }
                elseif (is_object($class))  {  $res.= 'object of class '.get_class($class).'->'.$method.'()';	 }
                else				        {  $res.= 'unknown "'.strval($variable).'"';						 }
            }
            else {
                $res.= 'unknown "'.strval($variable).'"';
            }
        }
        elseif ( is_array($variable) ){
            $res .= "<b>array</b>";
            foreach( $variable as $key => $value ){
                $res.="<div>";
                $res.="[ ".$key." ] = ".(is_array($value) ? self::format($value) : $value);
                $res.="</div>";
            }
            $res.="</ul>";
        }
        elseif (is_int($variable)){
            $res.="<b>integer</b> ";
            $res.=$variable;
        }
        elseif (is_bool($variable)){
            $res.="<b>bool</b>";
            if ( $variable )
                $res.="<i>True</i>";
            else
                $res.="<i>False</i>";
        }
        elseif (is_string($variable)){
            $res.= "<b>string</b>[".strlen($variable)."] ";
            $res.= "\"".htmlspecialchars($variable)."\"";
        }
        elseif (is_float($variable)){
            $res.= "<b>float</b> ";
            $res.= $variable;
        }
        elseif (is_resource($variable)){
            $res.= "<b>resource</b> ";
            $res.= '"'.get_resource_type($variable).'"';
        }
        elseif (is_object($variable)  ){
            $res.= "<b>object</b>[".get_class($variable)."] ";
            $res.= print_r($variable,1);
        }
        else{
            $res.= "<b>Unknown type</b>";
        }
        return $res;
    }
}
