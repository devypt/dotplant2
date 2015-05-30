<?php

namespace app\extensions\DefaultTheme\models;

use app\extensions\DefaultTheme\components\BaseWidget;
use app\traits\IdentityMap;
use Yii;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;
use \devgroup\TagDependencyHelper\ActiveRecordHelper;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%theme_parts}}".
 *
 * @property integer $id
 * @property string $name
 * @property string $key
 * @property integer $global_visibility
 * @property integer $multiple_widgets
 * @property integer $is_cacheable
 * @property integer $cache_lifetime
 * @property string $cache_tags
 * @property integer $cache_vary_by_session
 */
class ThemeParts extends \yii\db\ActiveRecord
{
    use IdentityMap;
    public static $allParts = null;
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => ActiveRecordHelper::className(),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%theme_parts}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['global_visibility', 'multiple_widgets', 'is_cacheable', 'cache_lifetime', 'cache_vary_by_session'], 'integer'],
            [['cache_tags'], 'string'],
            [['name', 'key'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'name' => Yii::t('app', 'Name'),
            'key' => Yii::t('app', 'Key'),
            'global_visibility' => Yii::t('app', 'Global Visibility'),
            'multiple_widgets' => Yii::t('app', 'Multiple Widgets'),
            'is_cacheable' => Yii::t('app', 'Is Cacheable'),
            'cache_lifetime' => Yii::t('app', 'Cache Lifetime'),
            'cache_tags' => Yii::t('app', 'Cache Tags'),
            'cache_vary_by_session' => Yii::t('app', 'Cache Vary By Session'),
        ];
    }

    /**
     * Returns all db-stored theme parts in array representation
     *
     * @param bool $force True if you want to refresh static-variable cache
     * @return array
     */
    public static function getAllParts($force = false)
    {
        if (static::$allParts=== null || $force === true) {
            $cacheKey = 'AllThemeParts';

            static::$allParts= Yii::$app->cache->get($cacheKey);
            if (static::$allParts=== false) {
                static::$allParts= ThemeParts::find()
                    ->where(['global_visibility'=>1])
                    ->asArray()
                    ->all();
                Yii::$app->cache->set(
                    $cacheKey,
                    static::$allParts,
                    86400,
                    new TagDependency([
                        'tags' => [
                            ActiveRecordHelper::getCommonTag(ThemeVariation::className()),
                        ]
                    ])
                );
            }
        }
        return static::$allParts;
    }

    public static function renderPart($key, $params=[])
    {
        $parts = static::getAllParts();

        /** @var ThemeParts $model */
        $model = null;
        foreach ($parts as $part) {
            if ($part['key'] === $key) {
                $model = $part;
                break;
            }
        }
        if ($model === null) {
            throw new InvalidConfigException("Can't find part with key $key");
        }

        if (static::shouldCache($model)) {
            $result = Yii::$app->cache->get(static::getCacheKey($model));
            if ($result !== false) {
                return $result . "<!-- cached -->";
            }
        }

        $model['id'] = intval($model['id']);

        $widgets = array_reduce(
            ThemeActiveWidgets::getActiveWidgets(),
            function ($carry, $item) use($model) {
                if ($item['part_id'] === $model['id']) {
                    $carry[]=$item;
                }
                return $carry;
            },
            []
        );
        ArrayHelper::multisort($widgets, 'sort_order');

        $result = array_reduce(
            $widgets,
            function($carry, ThemeActiveWidgets $activeWidget) use ($model, $params) {
                /** @var ThemeWidgets $widgetModel */
                $widgetModel = $activeWidget->widget;
                /** @var BaseWidget $widgetClassName */
                $widgetClassName =  $widgetModel->widget;
                $config = $params;
                $config['themeWidgetModel'] = $widgetModel;

                $carry .= $widgetClassName::widget($config);
                return $carry;
            },
            ''
        );

        if (static::shouldCache($model)) {
            Yii::$app->cache->set(
                static::getCacheKey($model),
                $result,
                $model['cache_lifetime'],
                static::getCacheDependency($model)
            );
        }

        return $result . '<!-- was uncached -->';
    }

    /**
     * @return bool True if we should cache this widget
     */
    public static function shouldCache($attributesRow)
    {
        return $attributesRow['is_cacheable']=== 1 && $attributesRow['cache_lifetime']> 0;
    }

    /**
     * @return string Cache key for this widget
     */
    public static function getCacheKey($attributesRow)
    {
        return "ThemePartCache:".$attributesRow['id'];
    }

    /**
     * @return string[] Array of cache tags
     */
    public static function getCacheTags($attributesRow)
    {
        $tags = explode("\n", $attributesRow['cache_tags']);
        $tags[] = ActiveRecordHelper::getObjectTag(ThemeParts::className(), $attributesRow['id']);
        return $tags;
    }

    /**
     * @return TagDependency TagDependency for cache storing
     */
    public static function getCacheDependency($attributesRow)
    {
        return new TagDependency([
            'tags' => static::getCacheTags($attributesRow),
        ]);
    }
}
