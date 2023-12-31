<?php

namespace app\components;

use GuzzleHttp\Client;

class Sports
{
    const BASE_URI = 'https://www.sports.ru/';
    const GATEWAY = 'gql/graphql/';
    const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36';
    const TIMEOUT = 10.0;

    /**
     * Возвращает все матчи сезона
     *
     * @param string $id ID турнира
     * @return array
     */
    public static function getMatches($id)
    {
        $data = self::sendGql("{
            statQueries {
                football {
                    tournament(id: \"{$id}\") {
                        currentSeason {
                            matches {
                                id
                                matchStatus
                                scheduledAt
                            }
                        }
                    }
                }
            }
        }");

        return $data->data->statQueries->football->tournament->currentSeason->matches;
    }

    /**
     * Возвращает данные матча
     *
     * @param string $id ID матча
     * @return array
     */
    public static function getMatch($id)
    {
        $data = self::sendGql("{
            statQueries {
                football {
                    match(id: \"{$id}\") {
                        id
                        home {
                            team {
                                name
                            }
                            score
                            lineup {
                                player {
                                    lastName
                                }
                                lineupStarting
                            }
                            isPreviewLineup
                        }
                        away {
                            team {
                                name
                            }
                            score
                            lineup {
                                player {
                                    lastName
                                }
                                lineupStarting
                            }
                            isPreviewLineup
                        }
                        events(eventType: [SCORE_CHANGE, RED_CARD, YELLOW_RED_CARD, PENALTY_MISSED, MATCH_ENDED]) {
                            id
                            type
                            value {
                                ... on statScoreChange {
                                    matchTime
                                    homeScore
                                    awayScore
                                    goalScorer {
                                        lastName
                                    }
                                    typeScore
                                    assist {
                                        lastName
                                    }
                                    team
                                }
                                ... on statRedCard {
                                    matchTime
                                    player {
                                        lastName
                                        name
                                    }
                                    team
                                }
                                ... on statYellowRedCard {
                                    matchTime
                                    player {
                                        lastName
                                        name
                                    }
                                    team
                                }
                                ... on statPenaltyMissed {
                                    matchTime
                                    player {
                                        lastName
                                    }
                                    team
                                }
                            }
                        }
                    }
                }
            }
        }");

        return $data->data->statQueries->football->match;
    }

    private static function sendGql($query)
    {
        $client = self::getClient();
        $response = $client->post(self::GATEWAY, [
            'json' => [
                'operationName' => null,
                'query' => $query,
                'variables' => null,
            ],
        ]);
        if ($response->getStatusCode() != 200) {
            return null;
        }

        $result = json_decode($response->getBody());
        return $result;
    }

    private static function getClient()
    {
        return new Client([
            'base_uri' => self::BASE_URI,
            'headers' => ['User-Agent' => self::USER_AGENT],
            'timeout' => self::TIMEOUT,
            'http_errors' => false,
        ]);
    }
}
