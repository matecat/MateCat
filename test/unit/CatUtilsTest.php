<?php


class CatUtilsTest extends AbstractTest {


    public function testRemovePhTagsFromTargetIfNotPresentInSource() {

        $source = "MateCat is a free and open source CAT tool (Computer-aided translation), that is a collaborative working platform for translators and project managers.";
        $targetL1 = "MateCat是一個免費的開源CAT工具（計算機輔助翻譯），它是翻譯人員和項目經理的協作工作平台。<ph id=\"mtc_1\" equiv-text=\"base64:JS1k\"/>";
        $targetL2 = "MateCat是一個免費的開源CAT工具（計算機輔助翻譯），它是翻譯人員和項目經理的協作工作平台。&lt;ph id=\"mtc_1\" equiv-text=\"base64:JS1k\"/&gt;";

        $removedL1 = CatUtils::removePhTagsFromTargetIfNotPresentInSource($source, $targetL1);
        $removedL2 = CatUtils::removePhTagsFromTargetIfNotPresentInSource($source, $targetL2);

        $expected = "MateCat是一個免費的開源CAT工具（計算機輔助翻譯），它是翻譯人員和項目經理的協作工作平台。%-d";

        $this->assertEquals($removedL1, $expected);
        $this->assertEquals($removedL2, $expected);
        $this->assertEquals($removedL2, $removedL1);
    }
}