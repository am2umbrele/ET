<?php

namespace App\Controllers;

use App\DB;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Elevator
{
    const FIRST_FLOOR = 0;
    const IDLE = 0;
    const MOVING = 1;

    /**
     * @var PDO
     */
    private $conn;

    /**
     * Elevator constructor.
     */
    public function __construct()
    {
        $instance = DB::getInstance();
        $this->conn = $instance->getConnection();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function scan(Request $request, Response $response): Response
    {
        $this->handleElevatorEnvLogic();

        $elevators = $this->getElevators();
        $totalFloors = $_ENV['FLOOR_COUNT'];
        $elevatorToMove = null;
        $requestBody = json_decode($request->getBody(), true);
        $floors = array_filter($requestBody['floors'], function ($value) use ($totalFloors) {
                return ($value >= 0 && $value <= $totalFloors);
            }
        );

        foreach ($elevators as $elevatorKey => $elevator) {
            ['floor' => $elevatorFloor] = $elevator;
            $bottomFloors = [];
            $topFloors = [];

            if ($elevatorFloor == self::FIRST_FLOOR) {
                $sortedFloors = $floors;
                sort($sortedFloors);
            } else {
                $sortedFloors = $floors;

                foreach ($sortedFloors as $sortedFloor) {
                    if ($elevatorFloor > $sortedFloor) {
                        $bottomFloors[] = $sortedFloor;
                    } else if ($elevatorFloor < $sortedFloor) {
                        $topFloors[] = $sortedFloor;
                    }
                }

                rsort($bottomFloors);
                sort($topFloors);
                $sortedFloors = array_merge($bottomFloors, $topFloors);
            }

            if (reset($sortedFloors) == $elevatorFloor) {
                unset($sortedFloors[0]);
                $sortedFloors = array_values($sortedFloors);
            }

            $seek = $this->getSeekTime($sortedFloors, $elevatorFloor);

            $elevators[$elevatorKey]['seek'] = $seek;
            $elevators[$elevatorKey]['floorsToGo'] = implode(',', $sortedFloors);
        }

        $columns = array_column($elevators, 'seek');
        array_multisort($columns, SORT_ASC, $elevators);

        $elevatorToMove = reset($elevators);
        $this->move($elevatorToMove);

        $response->getBody()->write(json_encode($elevatorToMove));
        $response = $response->withHeader('Content-Type', 'application/json');

        return $response;
    }

    public function move($elevatorToMove)
    {
        ['id' => $elevatorId, 'floor' => $elevatorFloor] = $elevatorToMove;
        $floorsRemaining = explode(',',$elevatorToMove['floorsToGo']);
        $lastFloorIndex = count($floorsRemaining) - 1;
        foreach ($floorsRemaining as $key => $floor) {
            $previousFloor = ($key == 0) ? $elevatorFloor : $floorsRemaining[$key - 1];

            $sql = "INSERT INTO elevator_move_log (elevator_id, floor_from, floor_to)
                    VALUES ($elevatorId, $previousFloor, $floor);
                    UPDATE elevators SET floor = $floor, status = " . self::MOVING . " WHERE id = $elevatorId";
            $this->conn->query($sql);

            if ($key === $lastFloorIndex) {
                $sql = "UPDATE elevators SET floor = $floor, status = " . self::IDLE . " WHERE id = $elevatorId";
                $this->conn->query($sql);
            }
        }
    }

    /**
     * @param array $sortedFloors
     * @param $elevatorFloor
     * @return float|int
     */
    public function getSeekTime(array $sortedFloors, $elevatorFloor)
    {
        $seek = 0;

        foreach ($sortedFloors as $floorKey => $floor) {
            if ($floorKey == 0) {
                $seek += abs($elevatorFloor - $sortedFloors[$floorKey]);
            } else {
                $seek += abs($sortedFloors[$floorKey] - $sortedFloors[$floorKey - 1]);
            }
        }

        return $seek;
    }

    /**
     * @return array
     */
    private function getElevators(): array
    {
        $sql = 'SELECT * FROM elevators';
        $elevators = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // select only idle elevators if any
        $idleElevators = array_filter($elevators, function ($elevator) {
            return $elevator['status'] == self::IDLE;
        });

        if (!empty($idleElevators)) {
            $elevators = $idleElevators;
        }
        return $elevators;
    }

    /**
     * @return void
     */
    private function handleElevatorEnvLogic(): void
    {
        // handle ELEVATOR_CAR_COUNT env logic for the database elevators table
        $sql = 'SELECT id FROM elevators';
        $elevators = $this->conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        sort($elevators);

        $existingElevatorCars = count($elevators);
        $elevatorCars = intval($_ENV['ELEVATOR_CAR_COUNT']);

        if (empty($elevators)) {
            $sql = 'INSERT INTO elevators (floor, status) VALUES ';
            for ($i = 1; $i <= $elevatorCars; $i++) {
                $sql .= "(0, 0),";
            }
            $this->conn->query(rtrim($sql, ','));
        } else {
            if ($existingElevatorCars < $elevatorCars) {
                $sql = 'INSERT INTO elevators (floor, status) VALUES ';
                for ($i = $elevatorCars - $existingElevatorCars + 1; $i <= $elevatorCars; $i++) {
                    $sql .= "(0, 0),";
                }
                $this->conn->query(rtrim($sql, ','));
            } else {
                $lastRowsToRemove = $existingElevatorCars - $elevatorCars;
                $elevatorsToRemove = array_slice($elevators, -$lastRowsToRemove, $lastRowsToRemove, true);
                $sql = "DELETE FROM elevators WHERE id IN (" . implode(',', $elevatorsToRemove) . ")";
                $this->conn->query($sql);
            }
        }
    }
}