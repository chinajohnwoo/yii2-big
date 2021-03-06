<?php
/**
 * @link http://www.bigbrush-agency.com/
 * @copyright Copyright (c) 2015 Big Brush Agency ApS
 * @license http://www.bigbrush-agency.com/license/
 */

use yii\helpers\Html;
use yii\grid\GridView;
use yii\data\ArrayDataProvider;
use yii\bootstrap\ButtonDropdown;
use yii\widgets\Pjax;
?>
<div class="row">
    <div class="col-md-12">
        <?= ButtonDropdown::widget([
            'label' => Yii::t('big', 'Select section'),
            'options' => ['class' => 'btn btn-info'],
            'dropdown' => [
                'options' => ['id' => 'sections-dropdown'],
                'items' => $buttons
            ]
        ]); ?>
    </div>
</div>

<div id="all-sections-wrap">
    <?php
    /**
     * counter used with BigSearch::createDropDownButtons()
     */
    $counter = 0;
    foreach ($sections as $section => $items) : ?>
    <div id="section-<?= $counter++ ?>" class="section-wrapper" style="display:none;">
        <div class="row">
            <div class="col-md-12">
                <?php Pjax::begin(); ?>
                <?= GridView::widget([
                    'dataProvider' => new ArrayDataProvider(['allModels' => $items]),
                    'columns' => [
                        [
                            'header' => Yii::t('big', 'Title'),
                            'format' => 'raw',
                            'options' => ['width' => '75%'],
                            'value' => function($data){
                                return Html::a($data['title'], '#', ['data-route' => $data['route'], 'class' => 'insert-on-click']);
                            },
                        ],
                        [
                            'header' => Yii::t('big', 'Section'),
                            'value' => function($data){
                                return $data['section'];
                            },
                        ],
                    ],
                ]); ?>
                <?php Pjax::end(); ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($fileManager !== null) : ?>
    <div id="section-<?= $counter++ ?>" class="section-wrapper" style="display:none;">
        <div class="row">
            <div class="col-md-12">
                <?= $fileManager ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>