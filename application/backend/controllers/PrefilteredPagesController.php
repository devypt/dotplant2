<?php

namespace app\backend\controllers;

use app\models\Config;
use app\models\Object;
use app\models\PrefilteredPages;
use app\models\Product;
use app\models\Property;
use app\models\PropertyGroup;
use app\models\PropertyStaticValues;
use vova07\imperavi\actions\GetAction;
use Yii;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;

class PrefilteredPagesController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['shop manage'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new PrefilteredPages();
        $dataProvider = $searchModel->search($_GET);

        return $this->render(
            'index',
            [
                'dataProvider' => $dataProvider,
                'searchModel' => $searchModel,
            ]
        );
    }

    public function actionEdit($id = null)
    {
        $model = new PrefilteredPages;
        $model->loadDefaultValues();

        if ($id !== null) {
            $model = PrefilteredPages::findOne($id);
        }

        $static_values_properties = [];

        $property_groups_ids_for_object = (new Query)->select('id')->from(PropertyGroup::tableName())->where(
                [
                    'object_id' => Object::getForClass(Product::className())->id,
                ]
            )->column();

        $properties = Property::find()->andWhere(['in', 'property_group_id', $property_groups_ids_for_object])->all();
        foreach ($properties as $prop) {
            $static_values_properties[$prop->id] = [
                'property' => $prop,
                'static_values_select' => PropertyStaticValues::getSelectForPropertyId($prop->id),
                'has_static_values' => $prop->has_static_values === 1,
            ];
        }

        $post = \Yii::$app->request->post();
        if ($model->load($post) && $model->validate()) {
            $save_result = $model->save();
            if ($save_result) {
                Yii::$app->session->setFlash('info', Yii::t('app', 'Object saved'));

                $returnUrl = Yii::$app->request->get(
                    'returnUrl',
                    ['/backend/prefiltered-pages/index', 'id' => $model->id]
                );
                switch (Yii::$app->request->post('action', 'save')) {
                    case 'next':
                        return $this->redirect(
                            [
                                '/backend/prefiltered-pages/edit',
                                'returnUrl' => $returnUrl,
                            ]
                        );
                    case 'back':
                        return $this->redirect($returnUrl);
                    default:
                        return $this->redirect(
                            Url::toRoute(
                                [
                                    '/backend/prefiltered-pages/edit',
                                    'id' => $model->id,
                                    'returnUrl' => $returnUrl,
                                ]
                            )
                        );
                }
                //return $this->redirect(['/backend/prefiltered-pages/edit', 'id' => $model->id]);

            } else {
                \Yii::$app->session->setFlash('error', Yii::t('app', 'Cannot update data'));
            }
        }

        return $this->render(
            'prefiltered-page-form',
            [
                'model' => $model,
                'static_values_properties' => $static_values_properties,
            ]
        );
    }

    public function actionDelete($id)
    {
        $model = PrefilteredPages::findOne($id);
        $model->delete();
        Yii::$app->session->setFlash('info', Yii::t('app', 'Object removed'));
        return $this->redirect(
            Url::to(
                [
                    '/backend/prefiltered-pages/index',
                ]
            )
        );
    }

    public function actionRemoveAll()
    {
        $items = Yii::$app->request->post('items', []);
        if (!empty($items)) {
            $items = PrefilteredPages::find()->where(['in', 'id', $items])->all();
            foreach ($items as $item) {
                $item->delete();
            }
        }

        return $this->redirect(['index']);
    }
}
