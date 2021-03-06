<?php

namespace Minicup\Model\Entity;

/**
 * @property int         $id
 * @property string|NULL $name                                       czech name of category
 * @property string      $slug                                       slug for URL
 * @property TeamInfo[]  $teamInfos m:belongsToMany                      actually teams in this category
 * @property Team[]      $teams m:belongsToMany                      actually teams in this category
 * @property Match[]     $matches m:belongsToMany                    matches in this category
 * @property Team[]      $allTeams m:belongsToMany(::category_id)    all historical teams in category
 * @property Year        $year m:hasOne                              year for this category
 * @property int         $default                                    flag if it's default category
 * @property Match[]     $confirmedMatches m:belongsToMany m:filter(confirmed) only confirmed matches
 */
class Category extends BaseEntity
{
    public static $CACHE_TAG = 'category';

    public const CATEGORY_URL_SPLITTER = '#([0-9]{4})-([\w]*)#';
    public const CATEGORY_URL_PATTEN = '%s-%s';

    /**
     * @return string
     * @throws \LeanMapper\Exception\InvalidStateException
     * @throws \LeanMapper\Exception\InvalidValueException
     * @throws \LeanMapper\Exception\MemberAccessException
     */
    public function getName()
    {
        return $this->get('name') ?: $this->slug;
    }
}
