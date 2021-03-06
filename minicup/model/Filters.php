<?php

namespace Minicup\Model;


use LeanMapper\Exception\InvalidArgumentException;
use LeanMapper\Fluent;
use Minicup\Model\Entity\Team;
use Minicup\Model\Repository\BaseRepository;
use Nette\SmartObject;

class Filters
{
    use SmartObject;

    /**
     * @param Fluent $fluent
     * @param int    $yearId
     */
    public function yearRestrict(Fluent $fluent, $yearId = NULL)
    {
        if ($yearId) {
            $fluent->where('[year_id] = %i', $yearId);
        }
    }

    /**
     * @param Fluent $fluent
     * @param Team   $team
     * @deprecated
     */
    public function joinAllMatches(Fluent $fluent, Team $team)
    {
        $fluent
            ->removeClause('where')
            ->where('[home_team_id] = ', $team->id, 'OR [away_team_id] =', $team->id);
    }

    /**
     * @param Fluent $fluent
     */
    public function joinTeamInfo(Fluent $fluent)
    {
        $fluent
            ->innerJoin('[team_info]')
            ->on('[team.team_info_id] = [team_info.id]')
            ->select('[team_info.name], [team_info.slug]');
    }

    /**
     * @param Fluent $fluent
     */
    public function actualTeam(Fluent $fluent)
    {
        $fluent->where('[team.actual] = 1');
    }

    /**
     * @param Fluent $fluent
     */
    public function orderTeams(Fluent $fluent)
    {
        $fluent->orderBy('[team.order] ASC');
    }

    /**
     * @param Fluent $fluent
     */
    public function confirmedMatch(Fluent $fluent)
    {
        $fluent->where('[match.confirmed] IS NOT NULL');
    }

    /**
     * @param Fluent $fluent
     */
    public function unconfirmedMatch(Fluent $fluent)
    {
        $fluent->where('[match.confirmed] IS NULL');
    }

    /**
     * @param Fluent $fluent
     * @param string $order
     * @throws InvalidArgumentException
     */
    public function orderMatches(Fluent $fluent, $order = BaseRepository::ORDER_ASC)
    {
        if (!in_array($order, [BaseRepository::ORDER_ASC, BaseRepository::ORDER_DESC], TRUE)) {
            throw new InvalidArgumentException('Invalid ordering method');
        }
        $fluent
            ->innerJoin('match_term')->as('mt')
            ->on('[match.match_term_id] = [mt.id]')
            ->innerJoin('day')->as('d')
            ->on('[d.id] = [mt.day_id]')
            ->orderBy("[d.day] $order, [mt.start] $order, [mt.location] $order, [match.id] $order");
    }

    /**
     * @param Fluent $fluent
     */
    public function activePhotos(Fluent $fluent)
    {
        $fluent->leftJoin('photo')->on('[photo_tag.photo_id] = [photo.id]')->where('[photo.active] = 1');
    }

    /**
     * @param Fluent $fluent
     * @param string $order
     * @throws InvalidArgumentException
     */
    public function orderPhotos(Fluent $fluent, $order = BaseRepository::ORDER_DESC)
    {
        if (!in_array($order, [BaseRepository::ORDER_ASC, BaseRepository::ORDER_DESC], TRUE)) {
            throw new InvalidArgumentException('Invalid ordering method');
        }
        $fluent->orderBy("[taken] $order");
    }

    /**
     * @param Fluent $fluent
     * @param string $order
     * @throws InvalidArgumentException
     */
    public function orderNews(Fluent $fluent, $order = BaseRepository::ORDER_DESC)
    {
        if (!in_array($order, [BaseRepository::ORDER_ASC, BaseRepository::ORDER_DESC], TRUE)) {
            throw new InvalidArgumentException('Invalid ordering method');
        }
        $fluent->orderBy("[added] $order");
    }

    /**
     * @param Fluent $fluent
     * @param string $order
     * @throws InvalidArgumentException
     */
    public function orderMatchEvents(Fluent $fluent, $order = BaseRepository::ORDER_DESC)
    {
        if (!in_array($order, [BaseRepository::ORDER_ASC, BaseRepository::ORDER_DESC], TRUE)) {
            throw new InvalidArgumentException('Invalid ordering method');
        }
        $fluent->orderBy("[half_index] $order, [time_offset] $order");
    }

    /**
     * @param Fluent $fluent
     * @param string $order
     * @throws InvalidArgumentException
     */
    public function orderPlayers(Fluent $fluent, $order = BaseRepository::ORDER_ASC)
    {
        if (!in_array($order, [BaseRepository::ORDER_ASC, BaseRepository::ORDER_DESC], TRUE)) {
            throw new InvalidArgumentException('Invalid ordering method');
        }
        $fluent->orderBy("[secondary_number] $order")->orderBy("`number` $order");
    }
}