<?php

/**
 * Created by PhpStorm.
 * User: dinies
 * Date: 29/03/16
 * Time: 17.22
 */
__halt_compiler();
class WorkSpaceTest extends AbstractTest
{

    public function testBOX()
    {
        {
            $source_segment = <<<LAB
<g id="1">[0054] </g><g id="2">y<g id="3">(</g>z</g><g id="4">1</g><g id="5">, t</g><g id="6">m</g><g id="7">) </g><g id="8">= d - r</g><g id="9">O                                                                                                                      </g><g id="10">(Equation 11)</g>
LAB;
            $expected_segment = <<<LAB
&lt;g id="1"&gt;[AH1]&lt;/g&gt;&lt;g id="2"&gt;Is fold & crease the same??&lt;/g&gt;
LAB;

            $this->assertEquals($expected_segment, CatUtils::rawxliff2view($source_segment));
        }

    }
}


      //setup
/*      $x = new Reflecto();
      $reflector = new ReflectionClass($x);
      //var_dump($reflector->getMethods());
      //var_dump($reflector->getProperties())
      //inc
      $func = $reflector->getMethod("inc");
      $func->setAccessible(true);
      $func->invoke($x);
      $func->invoke($x);
      //show
      $func = $reflector->getMethod("show");
      $func->invoke($x);                //int(42
      //set_register
      $func = $reflector->getProperty("register");
      $func->setAccessible(true);
      $func->setValue($x, "ciccio");
      $z = $func->getValue($x);
      var_dump("fineprimo");
      //call_user_func()
      call_user_func(array($x, "show"));           //NON FUNZIONA CON ROBE private !!
      var_dump("finesecondo");
      //indirect_reflection
      $name = "show";                                     //NON FUNZIONA CON ROBE private !!
      $x->$name();                                      //NON FUNZIONA CON ROBE private !!
      var_dump("fineterzo");

      class Reflecto
      {
          private $register=0;
          private function inc(){
              $this->register = $this->register + 21;
          }
          public function show(){
              var_dump($this-> register);
          }



   */









//    public function testgreppa(){
//
//
//
//        $inputseg_1= <<<'T'
//'<g id="1">イパス術を行うことで，経口で栄養状態を維持し，</g><g id="2">QOL</g>
//<g id="1">胃原発 </g><g id="2">DLBCL</g><g id="3"> の化学療法中に病変部の縮小によって</g>
//<g id="1">が病変部を通過することなく小腸に送られ，出血や穿孔 のリスクを減らし，自覚症状を軽減させる</g><g id="2">7)</g><g id="3">。</g>
//T;
//
//        $inputseg_2= <<<'T'
//        手術前後で <ex id="5"/><g id="6">Alb</g><g id="7"> </g><g id="8">2.7→3.7 g</g><g id="9">／</g><g id="10">dL</g><bx id="11"/> と体重 の改善を認め，治療を継続することができた。
//しかし，胃全摘は ダンピング症候群，貧血，骨代謝障害などの機能障害と， 胃内容のうっ滞，逆流性食道炎などの器質的障害を引き 起こし <ex id="1"/><g id="2">QOL</g><g id="3"> を低下させることから</g><g id="4">6)</g><bx id="5"/>，今回われわれは 胃空腸バイパス術（不完全離断型胃空腸吻合，梶谷法） を施行した。
//幽門輪だけでなく病変部位がいず<ex id="7"/><g id="8"> </g><g id="9">れであっても化学療法が奏効することによって狭窄が起 こり得るとされている</g><g id="10">5)</g><bx id="11"/>。
//T;
//        $inputseg_3= <<<'T'
//<g id="1">胃悪性リンパ腫の占拠部位は体部から幽門前庭で約 </g><g id="2">60</g><g id="3">〜</g><g id="4">70</g><g id="5">％を占める</g><g id="6">4)</g><bx id="7"/>。
//これは化学療法が奏効することによっ て病変が急速に壊死し，急速に縮小するためと考えられ ている<ex id="5"/><g id="6">3)</g><g id="7">。</g>
//<g id="1">治療は </g><g id="2">R-CHOP</g><g id="3"> 療法が行われるようになってきてい る</g><g id="4">2)</g><bx id="5"/>。
//T;
//        $inputseg_4= <<<'T'
//<g id="1">Lugano </g><g id="2">分類</g><g id="3"> stage </g><g id="4">Ⅰ〜Ⅱ</g><g id="5">1</g><g id="6">を限局期とした</g><g id="7"> DLBCL </g><g id="8">の</g>
//        現在では化学療法，放射線療法に加えて <ex id="1"/><g id="2">H.</g><g id="3"> </g><g id="4">pylori</g>
//<g id="1">併症や </g><g id="2">QOL</g><g id="3"> 低下の観点から外科切除は行わない傾向に</g>
//T;
//
//        $inputarr=array();
//        $inputarr[0]=$inputseg_1;
//        $inputarr[1]=$inputseg_2;
//        $inputarr[2]=$inputseg_3;
//        $inputarr[3]=$inputseg_4;
//
//
//        $matches=preg_grep('/([\xF0-\xF7]...)/s',$inputarr);
//        var_dump($matches);
//        $this->assertEmpty($matches);
//
//
//    }
