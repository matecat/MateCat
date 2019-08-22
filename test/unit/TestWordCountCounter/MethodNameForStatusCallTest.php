<?php

/**
 * @group regression
 * @covers WordCount_CounterModel::methodNameForStatusCall
 * User: dinies
 * Date: 13/06/16
 * Time: 12.34
 */
class MethodNameForStatusCallTest extends AbstractTest
{

    protected $word_counter;
    protected $mirror_word_counter;
    protected $method_methodNameForStatusCall;

    public function setUp(){
        $this->word_counter= new WordCount_CounterModel();
        $this->mirror_word_counter= new ReflectionClass($this->word_counter);
        $this->method_methodNameForStatusCall= $this->mirror_word_counter->getMethod('methodNameForStatusCall');
        $this->method_methodNameForStatusCall->setAccessible(true);
    }

    /**
     * @group regression
     * @covers WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "NEW"
     */
    public function test_methodNameForStatusCall_NEW(){

        $this->assertEquals("NewWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "NEW"));
    }/**
     * @group regression
     * @covers WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "DRAFT"
     */
    public function test_methodNameForStatusCall_DRAFT(){

        $this->assertEquals("DraftWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "DRAFT"));
    }/**
     * @group regression
 * @covers    WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "TRANSLATED"
     */
    public function test_methodNameForStatusCall_TRANSLATED(){

        $this->assertEquals("TranslatedWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "TRANSLATED"));
    }/**
     * @group regression
 * @covers    WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "APPROVED"
     */
    public function test_methodNameForStatusCall_APPROVED(){

        $this->assertEquals("ApprovedWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "APPROVED"));
    }/**
     * @group regression
 * @covers    WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "REJECTED"
     */
    public function test_methodNameForStatusCall_REJECTED(){

        $this->assertEquals("RejectedWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "REJECTED"));
    }/**
     * @group regression
 * @covers WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "FIXED"
     */
    public function test_methodNameForStatusCall_FIXED(){

        $this->assertEquals("TranslatedWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "FIXED"));
    }/**
 * @group     regression
 * @covers WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "REBUTTED"
     */
    public function test_methodNameForStatusCall_REBUTTED(){

        $this->assertEquals("TranslatedWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "REBUTTED"));
    }

    /**
     * @group  regression
     * @covers WordCount_CounterModel::methodNameForStatusCall
     *
     * @param "BARANDFOO"
     */
    public function test_methodNameForStatusCall_BARANDFOO(){

        $this->assertEquals("BarandfooWords", $this->method_methodNameForStatusCall->invoke($this->word_counter, "BARANDFOO"));
    }
}