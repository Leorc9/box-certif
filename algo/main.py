"""Main entry point for the trip optimization algorithm.

This file groups nearby cities first, then runs ACO only between hotel cities.
"""

from place import Place
from aco import AntColonyOptimizer
from cluster import clusterCities


def main():
    """Run the complete trip strategy example."""
    places = [
        Place("Tokyo", 35.6820172, 139.76216),
        Place("Nikko", 36.7198, 139.6983),
        Place("Kyoto", 34.9946315, 135.7344318),
        Place("Osaka", 34.6937, 135.5023),
        Place("Nara", 34.6851, 135.8048),
        Place("Nagoya", 35.1815, 136.9066),
        Place("Hiroshima", 34.3853, 132.4553),
        Place("Fukuoka", 33.5904, 130.4017),
        Place("Sapporo", 43.0618, 141.3545),
    ]

    cityClusters = clusterCities(places, maxRadius=150)
    hotelCities = [cluster.hotelCity for cluster in cityClusters]

    print(f"Hotel stops ({len(cityClusters)}):")

    for index, cityCluster in enumerate(cityClusters, 1):
        print(f"  {index}. {cityCluster}")

    optimizer = AntColonyOptimizer(hotelCities, nAnts=20, nIterations=100)
    bestTour, bestLength = optimizer.run()

    dayTripDistance = sum(
        cityCluster.getDayTripDistance()
        for cityCluster in cityClusters
    )

    totalDistance = bestLength + dayTripDistance

    route = " -> ".join(hotelCities[index].name for index in bestTour)
    route += " -> " + hotelCities[bestTour[0]].name

    print("\nOptimized route between hotels:")
    print(f"  {route}")
    print(f"  Inter-hotel distance: {bestLength:.1f} km")
    print(f"  Day-trip distance: {dayTripDistance:.1f} km")
    print(f"  Total distance: {totalDistance:.1f} km")


if __name__ == "__main__":
    main()