"""Entry point: build a list of places and run the ACO solver.

Run with:  python3 main.py
"""

from place import Place
from aco import AntColonyOptimizer


def main():
    # Small demo with a few Japanese cities (the subject uses Tokyo / Kyoto).
    places = [
        Place("Tokyo", 35.6820172, 139.76216),
        Place("Kyoto", 34.9946315, 135.7344318),
        Place("Osaka", 34.6937, 135.5023),
        Place("Nagoya", 35.1815, 136.9066),
        Place("Hiroshima", 34.3853, 132.4553),
        Place("Fukuoka", 33.5904, 130.4017),
        Place("Sapporo", 43.0618, 141.3545),
    ]

    optimizer = AntColonyOptimizer(places, nAnts=20, nIterations=100)
    bestTour, bestLength = optimizer.run()

    # Build a readable "A -> B -> ... -> A" string and print the result.
    route = " -> ".join(places[i].name for i in bestTour)
    route += " -> " + places[bestTour[0]].name  # back to the start
    print("Best tour found:")
    print(route)
    print(f"Total distance: {bestLength:.1f} km")


if __name__ == "__main__":
    main()
