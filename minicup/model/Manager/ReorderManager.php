<?php

namespace Minicup\Model\Manager;

use Minicup\Model\Entity\Category;
use Minicup\Model\Entity\Team;
use Minicup\Model\Repository\MatchRepository;
use Minicup\Model\Repository\TeamRepository;
use Nette\SmartObject;
use Tracy\Debugger;


/**
 * Class for conversion order
 * @package Minicup\Model\Manager
 */
class ReorderManager
{

    use SmartObject;
    const POINTS_FOR_WINNER = 2;
    const POINTS_FOR_DRAW = 1;
    const POINTS_FOR_LOSER = 0;

    /** @var TeamRepository */
    private $TR;

    /** @var MatchRepository */
    private $MR;

    /** @var Team[] */
    private $teams;

    /** @var Team[] */
    private $teamsEntities;

    /** @var array $teamPointsFromPoints */
    private $teamPointsFromPoints;

    /**
     * @param TeamRepository  $TR
     * @param MatchRepository $MR
     */
    public function __construct(TeamRepository $TR, MatchRepository $MR)
    {
        $this->TR = $TR;
        $this->MR = $MR;
    }

    /**
     * Get new team order
     *
     * @param Category $category
     */
    public function reorder(Category $category)
    {
        $this->teams = $this->TR->getByCategory($category);
        foreach ($this->teams as $team) {
            $this->teamsEntities[$team->id] = $team;
        }

        $this->orderByPoints();

        foreach ($this->teams as $team) {
            $this->TR->persist($team);
        }

        $this->debug();

        $this->teams = [];
        $this->teamsEntities = [];
        $this->teamPointsFromPoints = [];
    }

    /**
     * Reorder by team points from win or draw
     */
    private function orderByPoints()
    {
        $this->teamPointsFromPoints = [];
        foreach ($this->teams as $mainTeam) {
            $teamPoints = 0;
            foreach ($this->teams as $comparativeTeam) {
                if ($mainTeam->points > $comparativeTeam->points) {
                    $teamPoints += 1;
                }
            }
            $this->teamPointsFromPoints[$mainTeam->id] = $teamPoints;
        }

        // array; key == count of point; value == count of team with same points
        $pointScale = array_count_values($this->teamPointsFromPoints);
        $teamPosition = count($this->teams);

        $this->teamOrderByInternalPoints($pointScale, $teamPosition);
    }

    /**
     * Reorder team by internal points
     *
     * @param array       $pointScale
     * @param int         $teamPosition
     * @param array|NULL  $teamPoints
     * @param string|NULL $recursionFrom
     */
    private function teamOrderByInternalPoints($pointScale, $teamPosition, $teamPoints = NULL, $recursionFrom = NULL)
    {
        if ($teamPoints == NULL) {
            $teamPoints = $this->teamPointsFromPoints;
        }

        ksort($pointScale);
        if (count($pointScale) == 1 && $recursionFrom == NULL) {
            $teamsToCompare = $this->teamsToCompare($teamPoints);
            $countOfTeamsWithSamePoints = count($teamsToCompare);
            $this->orderByScoreDifference($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
        } elseif (count($pointScale) === 1 && $recursionFrom === '$this->miniTableWithScoreDifference') {
            $teamsToCompare = $this->teamsToCompare($teamPoints);
            $countOfTeamsWithSamePoints = count($teamsToCompare);
            $this->orderByScoreDifferenceFromMiniTable($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
        } elseif (count($pointScale) === 1 && $recursionFrom === '$this->miniTableWithScoreDifferenceFromMiniTable') {
            $teamsToCompare = $this->teamsToCompare($teamPoints);
            $countOfTeamsWithSamePoints = count($teamsToCompare);
            $this->orderByScored($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
        } else {
            foreach ($pointScale as $points => $countOfTeamsWithSamePoints) {
                if ($countOfTeamsWithSamePoints == 1) {
                    $teamID = array_search($points, $teamPoints, TRUE);
                    $this->getEntityOfTeam($teamID)->order = $teamPosition;
                } else if ($teamPoints == $this->teamPointsFromPoints) {
                    $this->orderByMutualMatch($countOfTeamsWithSamePoints, $points, $teamPosition);
                } else {
                    $this->orderByMutualMatch($countOfTeamsWithSamePoints, $points, $teamPosition, $teamPoints);
                }
                $teamPosition -= $countOfTeamsWithSamePoints;
            }
        }
    }

    /**
     * Do array with teams to compare
     *
     * @param array    $teamPoints
     * @param int|NULL $compare
     *
     * @return Team[]
     */
    private function teamsToCompare($teamPoints, $compare = NULL)
    {
        $teamsToCompare = [];
        foreach ($teamPoints as $key => $value) {
            if ($value == $compare OR $compare === NULL) {
                $teamsToCompare[] = $this->teamsEntities[$key];
            }
        }
        return $teamsToCompare;
    }

    /**
     * Reorder by difference ratio scored and received in fullTable
     *
     * @param array   $teamsToCompare
     * @param int     $countOfTeamsWithSamePoints
     * @param int     $teamPosition
     * @param boolean $mutualMatch
     */
    private function orderByScoreDifference($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition, $mutualMatch = TRUE)
    {
        if ($countOfTeamsWithSamePoints === 2) {
            if ($teamsToCompare[0]->scored - $teamsToCompare[0]->received === $teamsToCompare[1]->scored - $teamsToCompare[1]->received) {
                if ($mutualMatch === FALSE) {
                    $this->orderByScored($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
                } else {
                    $this->orderByScoreDifferenceFromMiniTable($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
                }
            } elseif ($teamsToCompare[0]->scored - $teamsToCompare[0]->received > $teamsToCompare[1]->scored - $teamsToCompare[1]->received) {
                $this->getEntityOfTeam($teamsToCompare[0]->id)->order = $teamPosition - 1;
                $this->getEntityOfTeam($teamsToCompare[1]->id)->order = $teamPosition;
            } else {
                $this->getEntityOfTeam($teamsToCompare[1]->id)->order = $teamPosition - 1;
                $this->getEntityOfTeam($teamsToCompare[0]->id)->order = $teamPosition;
            }
        } else {
            $this->miniTableWithScoreDifference($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
        }
    }

    /**
     * Reorder by difference scored goals in fullTable
     *
     * @param array $teamsToCompare
     * @param int   $countOfTeamsWithSamePoints
     * @param int   $teamPosition
     */
    private function orderByScored($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition)
    {
        $teamsScored = [];
        foreach ($teamsToCompare as $key => $team) {
            $teamsScored[$key] = $team->scored;
        }

        asort($teamsScored);
        if (\count(array_unique($teamsScored)) !== 1) {
            foreach ($teamsScored as $key => $teamScored) {
                $this->getEntityOfTeam($teamsToCompare[$key]->id)->order = $teamPosition;
                --$teamPosition;
            }
        } else {
            $this->setUnorderableTeams($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
        }
    }

    /**
     * Return team entity with $teamID
     *
     * @param int $teamID
     *
     * @return team
     */
    private function getEntityOfTeam($teamID): Team
    {
        return $this->teamsEntities[$teamID];
    }

    /**
     * Set same order for all teams
     *
     * @param array $teamsToCompare
     * @param int   $countOfTeamsWithSamePoints
     * @param int   $teamPosition
     */
    private function setUnorderableTeams($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition): void
    {
        $teamPosition -= ($countOfTeamsWithSamePoints - 1);
        foreach ($teamsToCompare as $team) {
            $team->order = $teamPosition;
        }
    }

    /**
     * Compare team score difference in mini table from mini table
     *
     * @param array $teamsToCompare
     * @param int   $countOfTeamsWithSamePoints
     * @param int   $teamPosition
     */
    private function orderByScoreDifferenceFromMiniTable($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition): void
    {
        if ($countOfTeamsWithSamePoints === 2) {
            $commonMatch = $this->MR->getCommonMatchForTeams($teamsToCompare[0], $teamsToCompare[1]);
            if ($commonMatch === NULL or $commonMatch->scoreHome - $commonMatch->scoreAway === 0) {
                $this->orderByScored($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
            } else {
                if ($teamsToCompare[0]->i->id === $commonMatch->homeTeam->id XOR $commonMatch->scoreHome - $commonMatch->scoreAway > 0) {
                    $this->getEntityOfTeam($teamsToCompare[0]->id)->order = $teamPosition - 1;
                    $this->getEntityOfTeam($teamsToCompare[1]->id)->order = $teamPosition;
                } else {
                    $this->getEntityOfTeam($teamsToCompare[1]->id)->order = $teamPosition - 1;
                    $this->getEntityOfTeam($teamsToCompare[0]->id)->order = $teamPosition;
                }
            }
        } else {
            $this->miniTableWithScoreDifferenceFromMiniTable($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
        }
    }

    /**
     * Compare team score difference in mini table from mini table
     *
     * @param array $teamsToCompare
     * @param int   $countOfTeamsWithSamePoints
     * @param int   $teamPosition
     */
    private function miniTableWithScoreDifferenceFromMiniTable($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition)
    {
        $teamPointsFromScore = [];
        foreach ($teamsToCompare as $team) {
            $teamPointsFromScore[$team->id] = 0;
        }
        for ($mainTeam = 0; $mainTeam < $countOfTeamsWithSamePoints - 1; $mainTeam++) {
            for ($comparedTeam = $mainTeam + 1; $comparedTeam < $countOfTeamsWithSamePoints; $comparedTeam++) {
                $commonMatch = $this->MR->getCommonMatchForTeams($teamsToCompare[$mainTeam], $teamsToCompare[$comparedTeam]);
                if ($commonMatch != NULL && $commonMatch->scoreHome - $commonMatch->scoreAway != 0) {
                    $score = $commonMatch->scoreHome - $commonMatch->scoreAway;
                    if ($teamsToCompare[array_search($teamsToCompare[$mainTeam], $teamsToCompare, TRUE)]->i->id == $commonMatch->homeTeam->id) {
                        $teamPointsFromScore[$teamsToCompare[$mainTeam]->id] += $score;
                        $teamPointsFromScore[$teamsToCompare[$comparedTeam]->id] += -$score;
                    } else {
                        $teamPointsFromScore[$teamsToCompare[$mainTeam]->id] += -$score;
                        $teamPointsFromScore[$teamsToCompare[$comparedTeam]->id] += $score;
                    }
                }
            }
        }
        $pointScale = array_count_values($teamPointsFromScore);
        $this->teamOrderByInternalPoints($pointScale, $teamPosition, $teamPointsFromScore, '$this->miniTableWithScoreDifferenceFromMiniTable');
    }

    /**
     * Compare team score difference in mini table from full table
     *
     * @param array $teamsToCompare
     * @param int   $countOfTeamWithSamePoints
     * @param int   $teamPosition
     */
    private function miniTableWithScoreDifference($teamsToCompare, $countOfTeamWithSamePoints, $teamPosition)
    {
        $teamPointsFromScore = [];
        foreach ($teamsToCompare as $mainTeam) {
            $teamPoints = 0;
            foreach ($teamsToCompare as $comparativeTeam) {
                if ($mainTeam->scored - $mainTeam->received > $comparativeTeam->scored - $comparativeTeam->received) {
                    $teamPoints += 1;
                }
            }
            $teamPointsFromScore[$mainTeam->id] = $teamPoints;
        }
        $pointScale = array_count_values($teamPointsFromScore);
        $this->teamOrderByInternalPoints($pointScale, $teamPosition, $teamPointsFromScore, '$this->miniTableWithScoreDifference');
    }

    /**
     * Reorder team by mutual match
     *
     * @param int        $countOfTeamsWithSamePoints
     * @param int        $points
     * @param int        $teamPosition
     * @param array|NULL $teamPoints
     */
    private function orderByMutualMatch($countOfTeamsWithSamePoints, $points, $teamPosition, $teamPoints = NULL)
    {
        if ($teamPoints === NULL) {
            $teamPoints = $this->teamPointsFromPoints;
        }

        $teamsToCompare = $this->teamsToCompare($teamPoints, $points);
        if ($countOfTeamsWithSamePoints === 2) {
            $commonMatch = $this->MR->getCommonMatchForTeams($teamsToCompare[0], $teamsToCompare[1]);
            if ($commonMatch !== NULL AND $commonMatch->scoreHome !== $commonMatch->scoreAway) {
                if ($teamsToCompare[0]->i->id === $commonMatch->homeTeam->id XOR $commonMatch->scoreHome > $commonMatch->scoreAway) {
                    $this->getEntityOfTeam($teamsToCompare[1]->id)->order = $teamPosition - 1;
                    $this->getEntityOfTeam($teamsToCompare[0]->id)->order = $teamPosition;
                } else {
                    $this->getEntityOfTeam($teamsToCompare[0]->id)->order = $teamPosition - 1;
                    $this->getEntityOfTeam($teamsToCompare[1]->id)->order = $teamPosition;
                }
            } else if (!$commonMatch) {
                $mutualMatch = FALSE;
                $this->orderByScoreDifference($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition, $mutualMatch);
            } else {
                $this->orderByScoreDifference($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
            }
        } else {
            $this->miniTableWithMutualMatch($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition);
        }
    }

    /**
     * Compare mutual match in table, for 3+ teams
     *
     * @param array $teamsToCompare
     * @param int   $countOfTeamsWithSamePoints
     * @param int   $teamPosition
     */
    private function miniTableWithMutualMatch($teamsToCompare, $countOfTeamsWithSamePoints, $teamPosition)
    {
        $teamPointsFromMiniTable = [];
        foreach (array_keys($teamsToCompare) as $key) {
            $teamPointsFromMiniTable[$key] = 0;
        }

        for ($mainTeam = 0; $mainTeam < $countOfTeamsWithSamePoints - 1; $mainTeam++) {
            for ($comparedTeam = $mainTeam + 1; $comparedTeam < $countOfTeamsWithSamePoints; $comparedTeam++) {
                $commonMatch = $this->MR->getCommonMatchForTeams($teamsToCompare[$mainTeam], $teamsToCompare[$comparedTeam]);
                if ($commonMatch !== NULL && $commonMatch->scoreHome !== $commonMatch->scoreAway) {
                    if ($teamsToCompare[$mainTeam]->i->id === $commonMatch->homeTeam->id XOR $commonMatch->scoreHome > $commonMatch->scoreAway) {
                        $teamPointsFromMiniTable[$comparedTeam] += static::POINTS_FOR_WINNER;
                        $teamPointsFromMiniTable[$mainTeam] += static::POINTS_FOR_LOSER;
                    } else {
                        $teamPointsFromMiniTable[$mainTeam] += static::POINTS_FOR_WINNER;
                        $teamPointsFromMiniTable[$comparedTeam] += static::POINTS_FOR_LOSER;
                    }
                } else if ($commonMatch !== NULL) {
                    $teamPointsFromMiniTable[$mainTeam] += static::POINTS_FOR_DRAW;
                    $teamPointsFromMiniTable[$comparedTeam] += static::POINTS_FOR_DRAW;
                }
            }
        }
        foreach ($teamsToCompare as $key => $team) {
            $teamPointsFromMiniTable[$team->id] = $teamPointsFromMiniTable[$key];
            unset ($teamPointsFromMiniTable[$key]);
        }

        $pointScale = array_count_values($teamPointsFromMiniTable);
        $this->teamOrderByInternalPoints($pointScale, $teamPosition, $teamPointsFromMiniTable);
    }

    /**
     * Debug barDump for testing
     */
    private function debug()
    {
        Debugger::barDump($this->teamPointsFromPoints, 'teamPointsFromPoints');
        foreach ($this->teams as $team) {
            Debugger::barDump('i: ' . $team->i->id . ', order: ' . $team->order, $team->id);
        }
    }

}