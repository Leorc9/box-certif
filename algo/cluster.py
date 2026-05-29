"""City clustering: group nearby cities around one hotel city.

The goal is to reduce hotel changes. Nearby cities are visited as day trips
from the same hotel city.
"""

from distance import greatCircleDistance


class CityCluster:
    """A hotel city with nearby cities that can be visited during the day."""

    def __init__(self, hotelCity):
        self.hotelCity = hotelCity
        self.dayTripCities = []

    def addDayTripCity(self, place):
        """Add a nearby city to visit without changing hotel."""
        self.dayTripCities.append(place)

    def getDayTripDistance(self):
        """Return the total round-trip distance for all day trips."""
        return sum(
            greatCircleDistance(self.hotelCity, city) * 2
            for city in self.dayTripCities
        )

    def __repr__(self):
        if self.dayTripCities:
            cityNames = ", ".join(city.name for city in self.dayTripCities)
            return f"{self.hotelCity.name} [day trips: {cityNames}]"

        return self.hotelCity.name


def clusterCities(places, maxRadius=150):
    """Group nearby cities around one hotel city.

    Args:
        places: List of Place objects.
        maxRadius: Maximum distance in km between the hotel city and a day-trip city.

    Returns:
        List of CityCluster objects.
    """
    remainingPlaces = list(places)
    cityClusters = []

    while remainingPlaces:
        hotelCity = remainingPlaces.pop(0)
        cityCluster = CityCluster(hotelCity)
        ungroupedPlaces = []

        for place in remainingPlaces:
            distanceFromHotel = greatCircleDistance(hotelCity, place)

            if distanceFromHotel <= maxRadius:
                cityCluster.addDayTripCity(place)
            else:
                ungroupedPlaces.append(place)

        remainingPlaces = ungroupedPlaces
        cityClusters.append(cityCluster)

    return cityClusters

if __name__ == "__main__":
    from place import Place

    places = [
        Place("Tokyo", 35.6820172, 139.76216),
        Place("Nikko", 36.7198, 139.6983),
        Place("Kyoto", 34.9946315, 135.7344318),
        Place("Osaka", 34.6937, 135.5023),
        Place("Nara", 34.6851, 135.8048),
        Place("Nagoya", 35.1815, 136.9066),
    ]

    cityClusters = clusterCities(places, maxRadius=150)

    print("City clusters for hotels:")

    for index, cityCluster in enumerate(cityClusters, 1):
        print(f"{index}. {cityCluster}")
        print(f"Day-trip distance: {cityCluster.getDayTripDistance():.1f} km")