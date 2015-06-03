<?php

namespace Minicup\Model\Entity;

/**
 * @property    int         $id
 * @property    Photo[]     $photos m:hasMany(:photo_tag) m:filter(activePhotos)    photos for this tag
 * @property    string|NULL $name                                                   name for tag
 * @property    string      $slug                                                   unique slug
 * @property    int         $isMain = 0                                             flag for gallery
 * @property    Photo|NULL  $mainPhoto m:hasOne(main_photo_id)                      main photo for tag
 * @property    TeamInfo|NULL   $teamInfo m:belongsToOne
 *
 * @method      addToPhotos
 * @method      removeFromPhotos
 * @method      removeAllPhotos
 */
class Tag extends BaseEntity
{
    public static $CACHE_TAG = 'tag';
}