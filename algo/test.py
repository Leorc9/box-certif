"""Simple assert-based tests for the Travel Planning ACO project.

Run with:  python3 test.py
Each function checks one part of the code. If an assert fails the program
stops and shows which check broke; otherwise it prints "All tests passed".
"""

import random

from place import Place
from distance import greatCircleDistance
from aco import AntColonyOptimizer


def testPlace():
    """A Place just stores its name and coordinates."""
    tokyo = Place("Tokyo", 35.68, 139.76)
    assert tokyo.name == "Tokyo"
    assert tokyo.latitude == 35.68
    assert tokyo.longitude == 139.76
    assert repr(tokyo) == "Tokyo"  # repr returns the name (see place.py)


def testDistance():
    """The great-circle distance must be 0, symmetric, and roughly correct."""
    tokyo = Place("Tokyo", 35.6820172, 139.76216)
    kyoto = Place("Kyoto", 34.9946315, 135.7344318)

    # Distance from a place to itself is 0 (and acos must not crash here).
    assert greatCircleDistance(tokyo, tokyo) == 0.0

    # Distance is the same both ways: d(A, B) == d(B, A).
    assert greatCircleDistance(tokyo, kyoto) == greatCircleDistance(kyoto, tokyo)

    # Real Tokyo -> Kyoto is about 370 km; we allow a small margin.
    d = greatCircleDistance(tokyo, kyoto)
    assert 360 < d < 385


def testDistanceMatrix():
    """The distance matrix has 0 on its diagonal and is symmetric."""
    places = [
        Place("Tokyo", 35.6820172, 139.76216),
        Place("Kyoto", 34.9946315, 135.7344318),
        Place("Osaka", 34.6937, 135.5023),
    ]
    opt = AntColonyOptimizer(places, nAnts=5, nIterations=5)

    for i in range(opt.n):
        assert opt.distances[i][i] == 0.0          # a place to itself
        for j in range(opt.n):
            assert opt.distances[i][j] == opt.distances[j][i]  # symmetric


def testTourLength():
    """A known tour over 3 places must give the expected total distance."""
    places = [
        Place("Tokyo", 35.6820172, 139.76216),
        Place("Kyoto", 34.9946315, 135.7344318),
        Place("Osaka", 34.6937, 135.5023),
    ]
    opt = AntColonyOptimizer(places, nAnts=5, nIterations=5)

    # Tour 0 -> 1 -> 2 -> back to 0. Computed value is ~816 km.
    length = opt._tourLength([0, 1, 2])
    assert abs(length - 816.1) < 1.0


def testBuildTour():
    """An ant's tour must be a valid order: every place exactly once, from 0."""
    places = [Place(str(i), i, i) for i in range(5)]  # 5 simple fake places
    opt = AntColonyOptimizer(places, nAnts=5, nIterations=5)

    tour = opt._buildTour()
    assert len(tour) == opt.n               # visits every place
    assert tour[0] == 0                     # always starts at place 0
    assert sorted(tour) == [0, 1, 2, 3, 4]  # no place missing or repeated


def testChooseNext():
    """_chooseNext must return a place that has not been visited yet."""
    places = [Place(str(i), i, i) for i in range(4)]
    opt = AntColonyOptimizer(places, nAnts=5, nIterations=5)

    visited = {0}
    chosen = opt._chooseNext(current=0, visited=visited)
    assert chosen not in visited            # it must be a new place
    assert chosen in (1, 2, 3)              # one of the remaining places


def testRun():
    """run() must return a valid tour and a positive, finite distance."""
    random.seed(0)  # fixed seed so the test always behaves the same way
    places = [
        Place("Tokyo", 35.6820172, 139.76216),
        Place("Kyoto", 34.9946315, 135.7344318),
        Place("Osaka", 34.6937, 135.5023),
        Place("Nagoya", 35.1815, 136.9066),
    ]
    opt = AntColonyOptimizer(places, nAnts=10, nIterations=20)

    tour, length = opt.run()
    assert sorted(tour) == [0, 1, 2, 3]     # a valid tour (each place once)
    assert length > 0                       # a real, positive distance
    assert length != float("inf")           # a best tour was actually found


if __name__ == "__main__":
    testPlace()
    print("Place ......... OK")
    testDistance()
    print("Distance ...... OK")
    testDistanceMatrix()
    print("Matrix ........ OK")
    testTourLength()
    print("Tour length ... OK")
    testBuildTour()
    print("Build tour .... OK")
    testChooseNext()
    print("Choose next ... OK")
    testRun()
    print("Run ........... OK")
    print("\nAll tests passed!")