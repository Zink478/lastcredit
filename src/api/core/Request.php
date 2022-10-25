<?php

namespace yii2custom\api\core;

class Request extends \yii\web\Request
{
    public function getQueryParams()
    {
        $params = parent::getQueryParams();
        foreach ($params as $key => &$param) {
            if (!is_string($param)) {
                return $params;
            }
            if ($param !== '' && in_array(substr($param, 0, 1), ['[', '{'])) {
                $result = $this->_loose_json_decode($param);
                $result && $result = (array)$result;
                if ($result !== null) {
                    $param = $result;
                }
            }
        }

        return $params;
    }

    private function _loose_json_decode($json)
    {
        $rgxjson = '%((?:\{[^\{\}\[\]]*\})|(?:\[[^\{\}\[\]]*\]))%';
        $rgxstr = '%("(?:[^"\\\\]*|\\\\\\\\|\\\\"|\\\\)*"|\'(?:[^\'\\\\]*|\\\\\\\\|\\\\\'|\\\\)*\')%';
        $rgxnum = '%^\s*([+-]?(\d+(\.\d*)?|\d*\.\d+)(e[+-]?\d+)?|0x[0-9a-f]+)\s*$%i';
        $rgxchr1 = '%^' . chr(1) . '\\d+' . chr(1) . '$%';
        $rgxchr2 = '%^' . chr(2) . '\\d+' . chr(2) . '$%';
        $chrs = array(chr(2), chr(1));
        $escs = array(chr(2) . chr(2), chr(2) . chr(1));
        $nodes = array();
        $strings = array();

        # escape use of chr(1)
        $json = str_replace($chrs, $escs, $json);

        # parse out existing strings
        $pieces = preg_split($rgxstr, $json, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 1; $i < count($pieces); $i += 2) {
            $strings [] = str_replace($escs, $chrs, str_replace(array('\\\\', '\\\'', '\\"'), array('\\', '\'', '"'), substr($pieces[$i], 1, -1)));
            $pieces[$i] = chr(2) . (count($strings) - 1) . chr(2);
        }
        $json = implode($pieces);

        # parse json
        while (1) {
            $pieces = preg_split($rgxjson, $json, -1, PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 1; $i < count($pieces); $i += 2) {
                $nodes [] = $pieces[$i];
                $pieces[$i] = chr(1) . (count($nodes) - 1) . chr(1);
            }
            $json = implode($pieces);
            if (!preg_match($rgxjson, $json)) break;
        }

        # build associative array
        for ($i = 0, $l = count($nodes); $i < $l; $i++) {
            $obj = explode(',', substr($nodes[$i], 1, -1));
            $arr = $nodes[$i][0] == '[';

            if ($arr) {
                for ($j = 0; $j < count($obj); $j++) {
                    if (preg_match($rgxchr1, $obj[$j])) $obj[$j] = $nodes[+substr($obj[$j], 1, -1)];
                    else if (preg_match($rgxchr2, $obj[$j])) $obj[$j] = $strings[+substr($obj[$j], 1, -1)];
                    else if (preg_match($rgxnum, $obj[$j])) $obj[$j] = +trim($obj[$j]);
                    else $obj[$j] = trim(str_replace($escs, $chrs, $obj[$j]));
                }
                $nodes[$i] = $obj;
            } else {
                $data = array();
                for ($j = 0; $j < count($obj); $j++) {
                    $kv = explode(':', $obj[$j], 2);
                    if (preg_match($rgxchr1, $kv[0])) $kv[0] = $nodes[+substr($kv[0], 1, -1)];
                    else if (preg_match($rgxchr2, $kv[0])) $kv[0] = $strings[+substr($kv[0], 1, -1)];
                    else if (preg_match($rgxnum, $kv[0])) $kv[0] = +trim($kv[0]);
                    else $kv[0] = trim(str_replace($escs, $chrs, $kv[0]));
                    if (preg_match($rgxchr1, $kv[1])) $kv[1] = $nodes[+substr($kv[1], 1, -1)];
                    else if (preg_match($rgxchr2, $kv[1])) $kv[1] = $strings[+substr($kv[1], 1, -1)];
                    else if (preg_match($rgxnum, $kv[1])) $kv[1] = +trim($kv[1]);
                    else $kv[1] = trim(str_replace($escs, $chrs, $kv[1]));
                    $data[$kv[0]] = $kv[1];
                }
                $nodes[$i] = $data;
            }
        }

        return $nodes[count($nodes) - 1];
    }
}