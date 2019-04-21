<?php

/* 
 * The MIT License
 *
 * Copyright 2019 Ibrahim BinAlshikh, restEasy library.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace restEasy;
/**
 * A class used to filter request parameters.
 * This class is the core class which is used to manage and set request 
 * parameters.
 * @author Ibrahim
 * @version 1.2.2
 */
class APIFilter{
    /**
     * Supported input types.
     * The filter supports the following data types:
     * <ul>
     * <li>string</li>
     * <li>integer</li>
     * <li>email</li>
     * <li>float</li>
     * <li>url</li>
     * <li>boolean</li>
     * <li>array</li>
     * </ul>
     * @var array 
     * @since 1.0
     */
    const TYPES = array(
        'string','integer','email','float','url','boolean','array'
    );
    /**
     * An array that will contains filtered data.
     * @var array
     * @since 1.0 
     */
    private $inputs = array();
    /**
     * An array that contains non-filtered data (original).
     * @var array
     * @since 1.2 
     */
    private $nonFilteredInputs = array();
    /**
     * Array that contains filter definitions.
     * @var array
     * @since 1.0 
     */
    private $paramDefs = array();
    /**
     * Adds a new request parameter to the filter.
     * @param RequestParameter $reqParam The request parameter that will be added.
     * @since 1.1
     */
    public function addRequestParameter($reqParam) {
        if($reqParam instanceof RequestParameter){
            $attribute = array(
                'parameter'=>$reqParam,
                'filters'=>array(),
                'options'=>array('options'=>array())
            );
            if($reqParam->getDefault() !== null){
                $attribute['options']['options']['default'] = $reqParam->getDefault();
            }
            if($reqParam->getCustomFilterFunction() != null){
                $attribute['options']['filter-func'] = $reqParam->getCustomFilterFunction();
            }
            $paramType = $reqParam->getType();
            if($paramType == 'integer'){
                if($reqParam->getMaxVal() !== null){
                    $attribute['options']['options']['max_range'] = $reqParam->getMaxVal();
                }
                if($reqParam->getMinVal() !== null){
                    $attribute['options']['options']['min_range'] = $reqParam->getMinVal();
                }
                array_push($attribute['filters'], FILTER_SANITIZE_NUMBER_INT);
                array_push($attribute['filters'], FILTER_VALIDATE_INT);
            }
            else if($paramType == 'string'){
                $attribute['options']['options']['allow-empty'] = $reqParam->isEmptyStringAllowed();
                array_push($attribute['filters'], FILTER_DEFAULT);
            }
            else if($paramType == 'float'){
                array_push($attribute['filters'], FILTER_SANITIZE_NUMBER_FLOAT);
            }
            else if($paramType == 'email'){
                array_push($attribute['filters'], FILTER_SANITIZE_EMAIL);
                array_push($attribute['filters'], FILTER_VALIDATE_EMAIL);
            }
            else if($paramType == 'url'){
                array_push($attribute['filters'], FILTER_SANITIZE_URL);
                array_push($attribute['filters'], FILTER_VALIDATE_URL);
            }
            else{
                array_push($attribute['filters'], FILTER_DEFAULT);
            }
            array_push($this->paramDefs, $attribute);
        }
    }
    /**
     * Returns an array that contains filter constraints.
     * @return array An array that contains filter constraints.
     * @since 1.2.2
     */
    public function getFilterDef() {
        return $this->paramDefs;
    }
    /**
     * Returns the boolean value of given input.
     * @param type $boolean
     * @return boolean|string
     * @since 1.1
     */
    private function _filterBoolean($boolean) {
        $booleanLwr = strtolower($boolean);
        $boolTypes = array(
            't'=>true,
            'f'=>false,
            'yes'=>true,
            'no'=>false,
            '-1'=>false,
            '1'=>true,
            '0'=>false,
            'true'=>true,
            'false'=>false,
            'on'=>true,
            'off'=>false,
            'y'=>true,
            'n'=>false,
            'ok'=>true);
        if(isset($boolTypes[$booleanLwr])){
            return $boolTypes[$booleanLwr];
        }
        return 'INV';
    }
    /**
     * Converts a string to an array.
     * @param string $array A string in the format '[3,"hello",4.8,"",44,...]'.
     * @return string|array If the string has valid array format, an array 
     * which contains the values is returned. If has invalid syntax, the 
     * method will return the string 'INV'.
     * @since 1.2.1
     */
    private static function _filterArray($array) {
        $len = strlen($array);
        $retVal = 'INV';
        $arrayValues = array();
        if($len >= 2){
            if($array[0] == '[' && $array[$len - 1] == ']'){
                $tmpArrValue = '';
                for($x = 1 ; $x < $len - 1 ; $x++){
                    $char = $array[$x];
                    if($x + 1 == $len - 1){
                        $tmpArrValue .= $char;
                        $number = self::checkIsNumber($tmpArrValue);
                        if($number != 'INV'){
                            $arrayValues[] = $number;
                        }
                        else{
                            return $retVal;
                        }
                    }
                    else{
                        if($char == '"' || $char == "'"){
                            $tmpArrValue = strtolower(trim($tmpArrValue));
                            if(strlen($tmpArrValue)){
                                if($tmpArrValue == 'true'){
                                    $arrayValues[] = true;
                                }
                                else if($tmpArrValue == 'false'){
                                    $arrayValues[] = false;
                                }
                                else if($tmpArrValue == 'null'){
                                    $arrayValues[] = null;
                                }
                                else{
                                    $number = self::checkIsNumber($tmpArrValue);
                                    if($number != 'INV'){
                                        $arrayValues[] = $number;
                                    }
                                    else{
                                        return $retVal;
                                    }
                                }
                            }
                            else{
                                $result = self::_parseStringFromArray($array, $x + 1, $len - 1, $char);
                                if($result['parsed'] == true){
                                    $x = $result['end'];
                                    $arrayValues[] = filter_var($result['string'], FILTER_SANITIZE_STRING);
                                    $tmpArrValue = '';
                                    continue;
                                }
                                else{
                                    return $retVal;
                                }
                            }
                        }
                        if($char == ','){
                            $tmpArrValue = strtolower(trim($tmpArrValue));
                            if($tmpArrValue == 'true'){
                                $arrayValues[] = true;
                            }
                            else if($tmpArrValue == 'false'){
                                $arrayValues[] = false;
                            }
                            else if($tmpArrValue == 'null'){
                                $arrayValues[] = null;
                            }
                            else{
                                $number = self::checkIsNumber($tmpArrValue);
                                if($number != 'INV'){
                                    $arrayValues[] = $number;
                                }
                                else{
                                    return $retVal;
                                }
                            }
                            $tmpArrValue = '';
                        }
                        else if($x + 1 == $len - 1){
                            $arrayValues[] = $tmpArrValue.$char;
                        }
                        else{
                            $tmpArrValue .= $char;
                        }
                    }
                }
                $retVal = $arrayValues;
            }
        }
        return $retVal;
    }
    /**
     * Checks if a given string represents an integer or float value. 
     * If the given string represents numeric value, the method will 
     * convert it to its numerical value.
     * @param string $str A value such as '1' or '7.0'.
     * @return string|int|double If the given string does not represents any 
     * numerical value, the method will return the string 'INV'. If the 
     * given string represents an integer, an integer value is returned. 
     * If the given string represents a floating point value, a float number 
     * is returned.
     */
    private static function checkIsNumber($str){
        $strX = trim($str);
        $len = strlen($strX);
        $isFloat = false;
        $retVal = 'INV';
        for($y = 0 ; $y < $len ; $y++){
            $char = $strX[$y];
            if($char == '.' && !$isFloat){
                $isFloat = true;
            }
            else if($char == '-' && $y == 0){
                
            }
            else if($char == '.' && $isFloat){
                return $retVal;
            }
            else{
                if(!($char <= '9' && $char >= '0')){
                    return $retVal;
                }
            }
        }
        if($isFloat){
            $retVal = floatval($strX);
        }
        else{
            $retVal = intval($strX);
        }
        return $retVal;
    }
    /**
     * Extract string value from an array that is formed as string.
     * It is a helper method that works with the method APIFilter::_parseStringFromArray().
     * @param type $arr
     * @param type $start
     * @param type $len
     * @return boolean
     * @since 1.2.1
     */
    private static function _parseStringFromArray($arr,$start,$len,$stringEndChar){
        $retVal = array(
            'end'=>0,
            'string'=>'',
            'parsed'=>false
        );
        $str = "";
        for($x = $start ; $x < $len ; $x++){
            $ch = $arr[$x];
            if($ch == $stringEndChar){
                $str .= "";
                $retVal['end'] = $x;
                $retVal['string'] = $str;
                $retVal['parsed'] = true;
                break;
            }
            else if($ch == '\\'){
                $x++;
                $nextCh = $arr[$x];
                if($ch != ' '){
                    $str .= '\\'.$nextCh;
                }
                else{
                    $str .= '\\ ';
                }
            }
            else{
                $str .= $ch;
            }
        }
        for($x = $retVal['end'] + 1 ; $x < $len ; $x++){
            $ch = $arr[$x];
            if($ch == ','){
                $retVal['parsed'] = true;
                $retVal['end'] = $x;
                break;
            }
            else if($ch != ' '){
                $retVal['parsed'] = false;
                break;
            }
        }
        return $retVal;
    }
    /**
     * Returns an associative array that contains request body inputs.
     * The data in the array will have the filters applied to.
     * @return array|null The array that contains request inputs. If no data was 
     * filtered, the method will return null.
     * @since 1.0
     */
    public function getInputs(){
        return $this->inputs;
    }
    /**
     * Returns the array that contains request inputs without filters applied.
     * @return array The array that contains request inputs.
     * @since 1.2
     */
    public final function getNonFiltered(){
        return $this->nonFilteredInputs;
    }

    /**
     * Filter GET parameters.
     * GET parameters are usually sent when request method is GET or DELETE.
     * @since 1.0
     */
    public final function filterGET(){
        foreach ($this->paramDefs as $def){
            $name = $def['parameter']->getName();
            if(isset($_GET[$name])){
                $toBeFiltered = strip_tags($_GET[$name]);
                $this->nonFilteredInputs[$name] = $toBeFiltered;
                if(isset($def['options']['filter-func'])){
                    $filteredValue = '';
                    $arr = array(
                        'original-value'=>$toBeFiltered,
                    );
                    if($def['parameter']->applyBasicFilter() === true){
                        if($def['parameter']->getType() == 'boolean'){
                            $filteredValue = $this->_filterBoolean(filter_var($toBeFiltered));
                        }
                        else if($def['parameter']->getType() == 'array'){
                            $filteredValue = $this->_filterArray(filter_var($toBeFiltered));
                        }
                        else{
                            $filteredValue = filter_var($toBeFiltered);
                            foreach ($def['filters'] as $val) {
                                $filteredValue = filter_var($filteredValue, $val, $def['options']);
                            }
                            if($filteredValue === false){
                                $filteredValue = 'INV';
                            }
                            if($def['parameter']->getType() == 'string' &&
                                    $filteredValue != 'INV' &&
                                    strlen($filteredValue) == 0 && 
                                    $def['options']['options']['allow-empty'] === false){
                                $this->inputs[$name] = 'INV';
                            }
                        }
                        $arr['basic-filter-result'] = $filteredValue;
                    }
                    else{
                        $filteredValue = 'INV';
                        $arr['basic-filter-result'] = 'NOT_APLICABLE';
                    }
                    $r = call_user_func($def['options']['filter-func'],$arr,$def['parameter']);
                    if($r === null){
                        $this->inputs[$name] = false;
                    }
                    else{
                        $this->inputs[$name] = $r;
                    }
                    if($this->inputs[$name] === false && $def['parameter']->getType() != 'boolean'){
                        $this->inputs[$name] = 'INV';
                    }
                }
                else{
                    if($def['parameter']->getType() == 'boolean'){
                        $this->inputs[$name] = $this->_filterBoolean(filter_var($toBeFiltered));
                    }
                    else if($def['parameter']->getType() == 'array'){
                        $this->inputs[$name] = $this->_filterArray(filter_var($toBeFiltered));
                    }
                    else{
                        $this->inputs[$name] = filter_var($toBeFiltered);
                        foreach ($def['filters'] as $val) {
                            $this->inputs[$name] = filter_var($this->inputs[$name], $val, $def['options']);
                        }
                        if($this->inputs[$name] === false){
                            $this->inputs[$name] = 'INV';
                        }
                        if($def['parameter']->getType() == 'string' &&
                                $this->inputs[$name] != 'INV' &&
                                strlen($this->inputs[$name]) == 0 && 
                                $def['options']['options']['allow-empty'] === false){
                            $this->inputs[$name] = 'INV';
                        }
                    }
                }
            }
            else{
                if($def['parameter']->isOptional()){
                    $defaultVal = $def['parameter']->getDefault();
                    if($defaultVal !== null){
                        $this->inputs[$name] = $defaultVal;
                    }
                }
            }
        }
    }
    /**
     * Filter POST parameters.
     * POST parameters are usually sent when request method is POST or PUT.
     * @since 1.0
     */
    public final function filterPOST(){
        foreach ($this->paramDefs as $def){
            $name = $def['parameter']->getName();
            if(isset($_POST[$name])){
                $toBeFiltered = strip_tags($_POST[$name]);
                $this->nonFilteredInputs[$name] = $toBeFiltered;
                if(isset($def['options']['filter-func'])){
                    $filteredValue = '';
                    $arr = array(
                        'original-value'=>$toBeFiltered,
                    );
                    if($def['parameter']->applyBasicFilter() === true){
                        if($def['parameter']->getType() == 'boolean'){
                            $filteredValue = $this->_filterBoolean(filter_var($toBeFiltered));
                        }
                        else if($def['parameter']->getType() == 'array'){
                            $filteredValue = $this->_filterArray(filter_var($toBeFiltered));
                        }
                        else{
                            $filteredValue = filter_var($toBeFiltered);
                            foreach ($def['filters'] as $val) {
                                $filteredValue = filter_var($filteredValue, $val, $def['options']);
                            }
                            if($filteredValue === false){
                                $filteredValue = 'INV';
                            }
                            if($def['parameter']->getType() == 'string' && 
                                    strlen($filteredValue) == 0 && 
                                    $def['options']['options']['allow-empty'] === false){
                                
                                $filteredValue = 'INV';
                            }
                        }
                        $arr['basic-filter-result'] = $filteredValue;
                    }
                    else{
                        $filteredValue = 'INV';
                        $arr['basic-filter-result'] = 'NOT_APLICABLE';
                    }
                    $r = call_user_func($def['options']['filter-func'],$arr,$def['parameter']);
                    if($r === null){
                        $this->inputs[$name] = false;
                    }
                    else{
                        $this->inputs[$name] = $r;
                    }
                    if($this->inputs[$name] === false && $def['parameter']->getType() != 'boolean'){
                        $this->inputs[$name] = 'INV';
                    }
                }
                else{
                    if($def['parameter']->getType() == 'boolean'){
                        $this->inputs[$name] = $this->_filterBoolean(filter_var($toBeFiltered));
                    }
                    else if($def['parameter']->getType() == 'array'){
                        $this->inputs[$name] = $this->_filterArray(filter_var($toBeFiltered));
                    }
                    else{
                        $this->inputs[$name] = filter_var($toBeFiltered);
                        foreach ($def['filters'] as $val) {
                            $this->inputs[$name] = filter_var($this->inputs[$name], $val, $def['options']);
                        }
                        if($this->inputs[$name] === false){
                            $this->inputs[$name] = 'INV';
                        }
                        if($def['parameter']->getType() == 'string' &&
                                $this->inputs[$name] != 'INV' &&
                                strlen($this->inputs[$name]) == 0 && 
                                $def['options']['options']['allow-empty'] === false){
                            $this->inputs[$name] = 'INV';
                        }
                    }
                }
            }
            else{
                if($def['parameter']->isOptional()){
                    $defaultVal = $def['parameter']->getDefault();
                    if($defaultVal !== null){
                        $this->inputs[$name] = $defaultVal;
                    }
                }
            }
        }
    }
    /**
     * Clears filter variables (parameters definitions, filtered inputs and non
     * -filtered inputs). 
     * @since 1.1
     */
    public function clear() {
        $this->paramDefs = array();
        $this->inputs = null;
        $this->nonFilteredInputs = null;
    }
}

