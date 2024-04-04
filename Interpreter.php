<?php
namespace IPP\Student;


use DOMDocument;
use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;
use IPP\Core\ReturnCode;





/**
 * Class SymbolTable
 * @package IPP\Student
 */
class SymbolTable {
    private mixed $variables = [];

    public function setVariable(mixed $name, mixed $value, mixed $type): void{
        
        $this->variables[$name] = [$value, $type];
    }

    
    public function getVariableType(mixed $name) : mixed {
        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name][1];
        }else{
            exit(ReturnCode::VARIABLE_ACCESS_ERROR);
        }
        // Handle undefined variable type
    }

    public function getVariable( mixed $name) : mixed {

        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name][0];
        }else{
            exit(ReturnCode::VARIABLE_ACCESS_ERROR);
        }
        // Handle undefined variable, throw exception or return a default value
    }

    public function variableExists(mixed $name):bool {
        return array_key_exists($name, $this->variables);
    }
}

/**
 * Class FrameManager
 * @package IPP\Student
 */
class FrameManager {
    private SymbolTable $GF; // Global Frame
    private array $LFStack = []; // Stack of Local Frames
    private SymbolTable $TF; // Temporary Frame
    private bool $isTFDefined = false;

    public function __construct() {
        $this->GF = new SymbolTable();
    }

    public function createTF(): void {
        $this->TF = new SymbolTable();
        $this->isTFDefined = true;
    }

    public function pushFrame() : void {
        if($this->isTFDefined) {
            
            array_push($this->LFStack, $this->TF);
            $this->TF = null;
            $this->isTFDefined = false;

        }else{
            
            exit(ReturnCode::FRAME_ACCESS_ERROR);
        }
    }
    public function getGF() : SymbolTable {
        return $this->GF;
    }
    
    public function getTF() : SymbolTable {
        if (!$this->isTFDefined) {
            exit(ReturnCode::FRAME_ACCESS_ERROR);
        }else{
            return $this->TF;
        }
    }

    public function getLF() {
        if(empty($this->LFStack)) {
            exit(ReturnCode::FRAME_ACCESS_ERROR);
        }
        return end($this->LFStack);
    }

    public function popFrame() {
        if(empty($this->LFStack)) {
            exit(ReturnCode::FRAME_ACCESS_ERROR);
        }
        $this->TF = array_pop($this->LFStack);
        $this->isTFDefined = true;
    }

    // Metody pro práci s GF, LF a TF
}

class LabelManager {
    

    private $labelPositions = []; 
    private $callStack = []; 
    
    public function addLabel($labelName, $position) {
        if( array_key_exists($labelName, $this->labelPositions)) {
            exit(ReturnCode::SEMANTIC_ERROR);
        }
        $this->labelPositions[$labelName] = $position;
    }

    
    public function getLabelPosition($labelName) {
        return $this->labelPositions[$labelName] ?? null;
    }

    
    public function pushCallPosition($position) {
        array_push($this->callStack, $position);
    }

    public function popCallPosition() {
        return array_pop($this->callStack);
    }
}
class Interpreter extends AbstractInterpreter
{
    private ?FrameManager $frameManager = null;
    private ?SymbolTable $symbolTable = null;
    private ?LabelManager $labelManager = null;
    private ?DataStack $dataStack = null;
    private int $curr_position;

    private function getFrameManager(): FrameManager{
        if ($this->frameManager === null) {
            $this->frameManager = new FrameManager();
        }
        return $this->frameManager;
    }

    private function getLabelManager(): LabelManager{
        if($this->labelManager === null) {
            $this->labelManager = new LabelManager();
        }
        return $this->labelManager;
    }

    private function getDataStack() : DataStack{
        if ($this->dataStack === null) {
            $this->dataStack = new DataStack();
        }
        return $this->dataStack;
    }

    public function addLabel(mixed $name, mixed $instruction): void{
        $this->getLabelManager()->addLabel($name, $instruction);
    }



    public function execute(): int
    {
        // TODO: Start your code here
       $orders = [];
       $real_instructions = [];
       $order_check = [];
       
            
        // Check \IPP\Core\AbstractInterpreter for predefined I/O objects:
            try {
                
                $dom = $this->source->getDOMDocument();
                
                $this->curr_position = 0;

                $instructions = $dom->documentElement->childNodes;

                foreach($instructions as $instruction) {
                    if($instruction->nodeType !== XML_ELEMENT_NODE){
                        $this->curr_position++;
                        continue;
                    } 
                    // how to trimm ale the whitespaces
                    // $instruction->nodeValue = trim($instruction->nodeValue);
                    // foreach($instruction->attributes as $attr) {
                    //     $attr->nodeValue = trim($attr->nodeValue);
                    // }

                    if($instruction->nodeName == "instruction") {
                        
                        foreach($instruction->childNodes as $arg) {
                            if($arg->nodeType !== XML_ELEMENT_NODE) continue;
                            $arg->nodeValue = trim($arg->nodeValue);
                        }

                        $orders[$instruction->getAttribute("order")] = $instruction->getAttribute("order");

                        if(array_key_exists($instruction->getAttribute("order"), $real_instructions)){
                            exit(ReturnCode::INVALID_SOURCE_STRUCTURE);
                        }
                        //give me regex check that order can only has + 1-9 and 0-9
                        if(preg_match('/^([1-9][0-9]*)$/', $instruction->getAttribute("order")) === 0){
                            exit(ReturnCode::INVALID_SOURCE_STRUCTURE);
                        }
                        
                        
                        $real_instructions[$instruction->getAttribute("order")] = $instruction;



                        
                        array_push($order_check, $instruction->getAttribute("order"));

                        if($instruction->getAttribute("opcode") == "LABEL") {
                            $this->addLabel($this->getArgs($instruction)[0][1], $instruction->getAttribute("order"));
                            
                        }
                        $this->curr_position++;
                    }else{
                        exit(ReturnCode::INVALID_SOURCE_STRUCTURE);
                    }
                }

                ksort($real_instructions);
                ksort($order_check);

                $unique_orders = array_unique(array_keys($real_instructions));
                
                if (count($unique_orders) !== count($real_instructions)) {
                    exit(32);
                }

                
                $this->curr_position = 0;
                $instructionCount = max($orders);
            
                
                

                while($this->curr_position <= $instructionCount) {

                    if(isset($orders[$this->curr_position])){

                    
                        $search = $orders[$this->curr_position];

                        if(isset($real_instructions[(string)$search])){

                            $instruction = $real_instructions[(string)$search];
                    
                        
                            if($instruction->nodeName == "instruction") {
                                
                                $this->parseOpcode($instruction);
                                
                            }
                        }
                    }
                    $this->curr_position++;
                    
                }
        
                
                return ReturnCode::OK; // Replace with actual success code
            } catch (\Exception $e) {
               
                exit(31); // Replace with actual error code
            }
    }

    private function parseOpcode($instruction):void {
        $opcode = $instruction->getAttribute("opcode");
        $opcode = strtoupper($opcode);
        switch ($opcode){
            case "MOVE":
                $this->parseMove($instruction);
                break;
            case "DEFVAR":
                $this->parseDefVar($instruction);
                break;
            case "WRITE":
                $this->parseWrite($instruction);
                break;
            // case "LABEL":
            //     $this-> parseLabel($instruction);
            //     break; 
            case "CREATEFRAME":
                $this->parseCreateFrame();
                break;
            case "PUSHFRAME":
                $this->parsePushFrame();
                break;
            case "POPFRAME":
                $this->parsePopFrame();
                break;
            case "CALL":
                $this->parseCall($instruction);
                break;
            case "RETURN":
                $this->parseReturn($instruction);
                break;
            case "PUSHS":
                $this->parsePUSHS($instruction);
                break;
            case "POPS":
                $this->parsePOPS($instruction);
                break;
            case "ADD":
                $this->parseArithmetic($instruction, "ADD");
                break;
            case "SUB":
                $this->parseArithmetic($instruction, "SUB");
                break;
            case "MUL":
                $this->parseArithmetic($instruction, "MUL");
                break;
            case "IDIV":
                $this->parseArithmetic($instruction, "IDIV");
                break;
            case "LT":
                $this->parseRelational($instruction, "LT");
                break;
            case "GT":
                $this->parseRelational($instruction, "GT");
                break;
            case "EQ":
                $this->parseRelational($instruction, "EQ");
                break;
            case "AND":
                $this->parseLogical($instruction, "AND");
                break;
            case "OR":
                $this->parseLogical($instruction, "OR");
                break;
            case "NOT":
                $this->parseLogical($instruction, "NOT");
                break;
            case "INT2CHAR":
                $this->parseInt2Char($instruction);
                break;
            case "READ":
                $this->parseRead($instruction);
                break;
            case "STRI2INT";
                $this->parseStri2Int($instruction);
                break;
            case "CONCAT":
                $this->parseConcat($instruction);
                break;
            case "JUMP":
                $this->parseJump($instruction);
                break;
            case "JUMPIFEQ":
                $this->parseJumpIfEq($instruction, $opcode);
                break;
            case "JUMPIFNEQ":
                $this->parseJumpIfEq($instruction, $opcode);
                break;
            case "GETCHAR":
                $this->parseGetChar($instruction);
                break;
            case "SETCHAR":
                $this->parseSetChar($instruction);
                break;
            case "EXIT":
                $this->parseExit($instruction);
                break;
            case "DPRINT":
                $this->parseDPRINT($instruction);
                break;
            case "BREAK":
                $this->parseBreak($instruction);
                break;
            

            
            
            

        }
    }

    private function parseBreak($instruction):void {
        $this->stderr->writeString("Breakpoint at lane:" . $this->curr_position . "\n");
    }

    private function parseDPRINT( mixed $instruction):void {
        $args = $this->getArgs($instruction);
        $sym = $args[0][1];
        $symType = $this->getType($args[0]);
        if($symType === "var"){
            $sym = $this->getValue($args[0]);
        }
        if($symType === "string"){
            $sym = $this->replaceEscapeSequences($sym);
        }
        $this->stderr->writeString("$sym\n");
    }

    private function parseExit( mixed $instruction):void {
        $args = $this->getArgs($instruction);
        $symType = $this->getType($args[0]);
        if($symType !== "int"){
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }
        $sym = $this->getValue($args[0]);
        if($sym < 0 || $sym > 9){
            exit(ReturnCode::OPERAND_VALUE_ERROR);
        }
        exit($sym);

    }

    private function parseConcat( mixed $instruction):void {
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        $symType = $this->getType($args[1]);
        $symType2 = $this->getType($args[2]);

        if($symType !== "string" || $symType2 !== "string"){
            exit(ReturnCode::SEMANTIC_ERROR);
        }
        list( $frame_var, $var ) = explode("@", $var, 2);
        $frame_var = $this->getFrame( $frame_var );
        $sym = $this->getValue($args[1]);
        $sym2 = $this->getValue($args[2]);

        $frame_var->SetVariable( $var, "$sym" . "$sym2", "string");
    }

    private function parseRelational( $instruction,  $opcode):void {
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        if(!$this->variableExists($args[0]) || !$this->variableExists($args[1]) || !$this->variableExists($args[2])) {
            exit(ReturnCode::VARIABLE_ACCESS_ERROR);
        }
        $sym1 = $this->getValue($args[1]);
        $sym2 = $this->getValue($args[2]);

        $symType1 = $this->getType($args[1]);
        $symType2 = $this->getType($args[2]);

        if($symType1 !== $symType2) {
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }
        list($frame_var, $var) = explode("@", $var, 2);
        $frame_var = $this->getFrame($frame_var);

        switch($opcode){
            case "LT":
                $frame_var->setVariable($var, $sym1 < $sym2, "bool");
                break;
            case "GT":
                $frame_var->setVariable($var, $sym1 > $sym2, "bool");
                break;
            case "EQ":
                $frame_var->setVariable($var, $sym1 == $sym2, "bool");
                
                break;
        }
       

    }
    private function replaceEscapeSequences(mixed $text): mixed {
       

        // Nahrazení escape sekvencí ASCII hodnotami
        $decodedText = preg_replace_callback(
        '/\\\\(\d{3})/', // Hledá escape sekvence ve formátu \nnn, kde nnn je číselná hodnota
        function ($matches) {
            return chr($matches[1]); // Převede číselnou hodnotu na znak
        }, $text);

        return $decodedText;
    }

    private function parseWrite( mixed $instruction):void {
        
        
        $args = $this->getArgs($instruction);
        
        $sym = $args[0][1];

        
        $symType = $this->getType($args[0]);
        // Split
        if ($symType === "var") {
            list($sym_prefix, $sym) = explode("@", $sym, 2);
            $sym_prefix = $this->getFrame($sym_prefix);
            
        }
        
        else{
            if(list($sym_prefix, $sym) = explode("@", $sym, 2)[0] === null){
                switch($symType){
                    case "int":
                        $this->stdout->writeString("$sym");
                        break;
                    case "bool":
                        $this->stdout->writeString(($sym == "true" ? "true" : "false"));
                        break;
                    case "string":
                       
                        $sym = $this->replaceEscapeSequences($sym);
                        $this->stdout->writeString("$sym");
                        break;
                    case "nil":
                        $this->stdout->writeString("nil");
                        break;
                }
            }else{

                $sym = $this->getValue($args[0]);
                if($symType === "string"){
                    $sym = $this->replaceEscapeSequences($sym);
                }elseif($symType === "bool"){
                    $sym = $sym == "true" ? "true" : "false";
                }
                
                $this->stdout->writeString("$sym" );
            }
        }

    }

    private function parseInt2Char( mixed $instruction): void{
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        $symType = $this->getType($args[1]);

        if($symType !== "int"){
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }
        $sym = $this->getValue($args[1]);
        if($sym < 0){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        list($frame_var, $var) = explode("@", $var, 2);
        $frame_var = $this->getFrame($frame_var);
        $frame_var->setVariable($var, chr($sym), "string");
    }

    // this function does store char at index(3.arg) from string(2.arg) to variable
    private function parseStri2Int(mixed $instruction): void{
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        $symType = $this->getType($args[1]);
        if ( $symType !== "string"){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        $symType2 = $this->getValue($args[2]);
        if($symType2 !== "int"){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        $sym = $this->getValue($args[1]);
        $sym2 = $this->getValue($args[2]);
        if($sym2 < 0 || $sym2 >= strlen($sym)){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }

        list($frame_var , $var) = explode("@" , $var ,2);
        $frame_var = $this->getFrame($frame_var);
        if($frame_var->variableExists($var) === false){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        
        $frame_var->setVariable($var, ord($sym), "int");



    }

    private function parseGetChar(mixed $instruction): void{
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        $symType = $this->getType($args[1]);
        $symType2 = $this->getType($args[2]);
        if($symType !== "string" || $symType2 !== "int"){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        $sym = $this->getValue($args[1]);
        $sym2 = $this->getValue($args[2]);
        if($sym2 < 0 || $sym2 >= strlen($sym)){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        list($frame_var, $var) = explode("@", $var, 2);
        $frame_var = $this->getFrame($frame_var);
        $frame_var->setVariable($var, $sym[$sym2], "string");
    }

    private function parseSetChar(mixed $instruction): void{
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        $symType = $this->getType($args[1]);
        $symType2 = $this->getType($args[2]);
        $symType3 = $this->getType($args[3]);
        if($symType !== "string" || $symType2 !== "int" || $symType3 !== "string"){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        $sym = $this->getValue($args[1]);
        $sym2 = $this->getValue($args[2]);
        $sym3 = $this->getValue($args[3]);
        if($sym2 < 0 || $sym2 >= strlen($sym)){
            exit(ReturnCode::STRING_OPERATION_ERROR);
        }
        list($frame_var, $var) = explode("@", $var, 2);
        $frame_var = $this->getFrame($frame_var);
        $frame_var->setVariable($var, substr_replace($sym, $sym3, $sym2, 1), "string");
    }
   
    private function parseRead(mixed $instruction): void{
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        $symType = $args[1][1];

        switch($symType){
            case "int":
                $value = $this->input->readInt();
                break;
            case "string":
                $value = $this->input->readString();
                break;
            case "bool":
                $value = $this->input->readBool();
                break;
            default:
                exit(69);
        }
        if($value === null){
            $symType = "nil";
        }

        list($frame_var, $var) = explode("@", $var ,2);
        $frame_var = $this->getFrame($frame_var);
        if($frame_var->variableExists($var) === false){
            exit(ReturnCode::SEMANTIC_ERROR);   
        }
        $frame_var->setVariable($var, $value, $symType);
    }
    private function parseDefVar( mixed $instruction):void {
        
        $argCount = 0;

        foreach($instruction->childNodes as $arg) {
            if($arg->nodeType !== XML_ELEMENT_NODE) continue;
            if($arg->nodeName == "arg1") {
                
                $value = $arg->nodeValue;
                
                
            }else{
                exit(32);
            }
            $argCount = $argCount + 1;
        }
        
        if($argCount === 0){
            exit(32);
        }
        

        list($frame, $value) = explode("@", $value, 2); // Split

        $frame = $this->getFrame($frame);
         // get frame
 
        if ($frame->variableExists($value) === false) { // Semantic check before adding
            $frame->setVariable($value, null, null);
        }else{
            exit(ReturnCode::SEMANTIC_ERROR);
        }  
    }

    private function getFrame(mixed $frame): mixed { // Get frame function
        if($frame == "GF") {
            return $this->getFrameManager()->getGF();
        }elseif($frame == "LF") {
            return $this->getFrameManager()->getLF();
        }elseif($frame == "TF") {
            return $this->getFrameManager()->getTF();
        }else{
            return ReturnCode::FRAME_ACCESS_ERROR;
        }
    }
    private function getArgs(mixed $instruction) : mixed {
        $args = [];

        foreach($instruction->childNodes as $arg) {
            if($arg->nodeType !== XML_ELEMENT_NODE) continue;
            $tagName = $arg->nodeName;
            // make me regex to check if the arg is valid argnumbers , where first number can be 1-9 second 0-9
            if (preg_match('/^arg(\d+)$/', $tagName, $matches) === 0){
                exit(ReturnCode::INVALID_SOURCE_STRUCTURE);
            }

            $argIndex = (int)$matches[1] - 1;
            $type = $arg->getAttribute("type");
            $value = $arg->nodeValue;
            $args[$argIndex] = [$type, $value];
        }
        ksort($args);
        return $args;
    }
    // private function isConstant($sym_prefix){

    //     if($sym_prefix == "int" || $sym_prefix == "bool" || $sym_prefix == "string" || $sym_prefix == "nil") {
    //         return true;
    //     }else{
    //         return false;
    //     }

    // }
    
    
    private function parseMove(mixed $instruction): void {
        
        $args = $this->getArgs($instruction);
        
        $var = $args[0][1];
        $varType = $args[0][0];


        if($varType !== "var") {
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }

        $sym = $args[1][1];
        $symType = $args[1][0];


        
        list($frame_var, $var) = explode("@", $var, 2);
         // Split
        $frame_var = $this->getFrame($frame_var); // get frame
        
        if( $symType !== "var") {

            $frame_var->setVariable($var, $sym, $symType);
            
        }else{
            
            list($sym_prefix, $sym) = explode("@", $sym, 2); // Split
            $sym_prefix = $this->getFrame($sym_prefix);
            $frame_var->setVariable($var, $sym_prefix->getVariable($sym), $symType);
        }

    }

    private function parseCall(mixed $instruction): void{
        
        $args = $this->getArgs($instruction);
        $label = $args[0][1];
        $position = $this->getLabelManager()->getLabelPosition($label);
        if($position == null) {
            exit(ReturnCode::SEMANTIC_ERROR);
        }
        $curr_position = $instruction->getAttribute("order");
        $this->getLabelManager()->pushCallPosition($curr_position + 1);
        $this->curr_position = $position;

    } // KUKNI SA NA TEXT.XML KKOKOT

    
    private function parseReturn($instruction) : void {
       
        $position = $this->getLabelManager()->popCallPosition();
        if($position == null) {
            exit(ReturnCode::VALUE_ERROR);
        }
        $this->curr_position = $position;
    }

    private function parseCreateFrame(): void {
       
        $this->getFrameManager()->createTF();
    }

    private function parsePushFrame():void {
        
        $this->getFrameManager()->pushFrame();
    }

    private function parsePopFrame(): void {
       
        $this->getFrameManager()->popFrame();
    }

    private function parsePUSHS( mixed $instruction): void {
        $args = $this->getArgs($instruction);
        $arg_value = $args[0][1];
        $arg_type = $this->getType($args[0]);
        $this->getDataStack()->push($arg_value, $arg_type);
    }

    private function parsePOPS(mixed $instruction):void {
        $args = $this->getArgs($instruction);
        $arg_to_store = $args[0][1];
        list($frame, $arg_to_store) = explode("@", $arg_to_store, 2);
        $frame = $this->getFrame($frame);
        if($frame->variableExists($arg_to_store) === false){
            exit(ReturnCode::VARIABLE_ACCESS_ERROR);
        }
        $poped = $this->getDataStack()->pop();
        
        $frame->setVariable($arg_to_store, $poped[0] , $poped[1]);
        
    }

    private function getValue(mixed $symbol): mixed{

        if($symbol[0] == "var") {
            list($frame, $var) = explode("@", $symbol[1], 2);
            $frame = $this->getFrame($frame);
            return $frame->getVariable($var);
        }else{
            if($symbol[0] == "int"){
                
                //check if the value is in the correct format for int
                if(preg_match('/^[-+]?[0-9]+$/', $symbol[1]) === 0){
                    exit(ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                
            }
            return $symbol[1];
        }
    }

    private function getType(mixed $symbol): mixed{
        if($symbol[0] == "var") {
            list($frame, $var) = explode("@", $symbol[1], 2);
            $frame = $this->getFrame($frame);
            return $frame->getVariableType($var);
        }else{
            return $symbol[0];
        }
    }

    private function parseJump(mixed $instruction): void{
        $args = $this->getArgs($instruction);
        $label = $args[0][1];
        $position = $this->getLabelManager()->getLabelPosition($label);
        if($position === null){
            exit(ReturnCode::SEMANTIC_ERROR);
        }
        $this->curr_position = $position;

        
    }

    private function parseJumpIfEq(mixed $instruction, $opcode):void {
        $args = $this->getArgs($instruction);
        $label = $args[0][1];
        $position = $this->getLabelManager()->getLabelPosition($label);
        if($position === null){
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }
        $symType = $this->getType($args[1]);
        $symType2 = $this->getType($args[2]);
        $sym1 = $this->getValue($args[1]);
        $sym1 = $sym1 . '';
        $sym2 = $this->getValue($args[2]);
        if(!($symType === $symType2))
        {
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }

        if($opcode === "JUMPIFEQ")
        {
            if($sym1 === $sym2){
                $this->curr_position = $position;
            }
        }else if($opcode === "JUMPIFNEQ")
        {
            if($sym1 !== $sym2){
                $this->curr_position = $position;
            }
        }
            

    }

    private function variableExists($symbol){
        if($symbol[0] == "var") {
            list($frame, $var) = explode("@", $symbol[1], 2);
            $frame = $this->getFrame($frame);
            return $frame->variableExists($var);
        }else{
            return true;
        }
    }
    
    private function parseArithmetic( mixed $instruction, mixed $opcode): void{
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        if(!$this->variableExists($args[0]) || !$this->variableExists($args[1]) || !$this->variableExists($args[2])) {
            exit(ReturnCode::VARIABLE_ACCESS_ERROR);
        }

        $sym1 = $this->getValue($args[1]);
        $sym2 = $this->getValue($args[2]);
        $symType1 = $this->getType($args[1]);
        $symType2 = $this->getType($args[2]);
        if($symType1 !== "int" || $symType2 !== "int") {
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }
        list($frame_var, $var) = explode("@", $var, 2);
        $frame_var = $this->getFrame($frame_var);
        
        switch($opcode){
            case "ADD":
                $frame_var->setVariable($var, $sym1 + $sym2, $symType1);
                break;
            case "SUB":
                $frame_var->setVariable($var, $sym1 - $sym2, $symType1);
                break;
            case "MUL":
                $frame_var->setVariable($var, $sym1 * $sym2, $symType1);
                break;
            case "IDIV":
                if($sym2 === "0") {
                    exit(ReturnCode::OPERAND_VALUE_ERROR);
                }
                $frame_var->setVariable($var, $sym1 / $sym2, $symType1);
                break;
        }
    
    }

    private function parseLogical(mixed $instruction,mixed $opcode):void {
        $args = $this->getArgs($instruction);
        $var = $args[0][1];
        $symType1 = $this->getType($args[1]);
        if(!$this->variableExists($args[0]) || !$this->variableExists($args[1]) || !$this->variableExists($args[2])) {
            exit(ReturnCode::VARIABLE_ACCESS_ERROR);
        }
        if($opcode !== "NOT"){
            $symType2 = $this->getType($args[2]);
            if($symType2 !== "bool") {
                exit(ReturnCode::OPERAND_TYPE_ERROR);
            }
        }
        if($symType1 !== "bool") {
            exit(ReturnCode::OPERAND_TYPE_ERROR);
        }
        $sym1 = $this->getValue($args[1]);
        $sym2 = $this->getValue($args[2]);

        list($frame_var, $var) = explode("@", $var, 2);
        $frame_var = $this->getFrame($frame_var);
        switch($opcode){
            case "AND":
                $frame_var->setVariable($var, $sym1 && $sym2, "bool");
                break;
            case "OR":
                $frame_var->setVariable($var, $sym1 || $sym2, "bool");
                break;
            case "NOT":
                $frame_var->setVariable($var, !$sym1, "bool");
                break;

        }


    }
}





class DataStack {
    private $stack = [];
    // code stack which will be used to store the data
    public function push(mixed $data, mixed $type): void {
        array_push($this->stack, [$data,$type]);
    }

   // code function to get the top only if the stack is not empty

    public function pop() : mixed{
        if (empty($this->stack)) {
            exit(ReturnCode::VALUE_ERROR);
        }
        return array_pop($this->stack);
    }


    public function isEmpty() : bool {
        return empty($this->stack);
    }

}



?>
