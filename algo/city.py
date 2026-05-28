from math import sqrt
class City():
    def __init__(self, longitude: float, latitude: float):
        self.longitude = longitude
        self.latitude = latitude

    def distance(self, other):
        return sqrt((other.longitude-self.longitude)**2 + (other.latitude-self.latitude)**2)


c1 = City(10,10)
c2 = City (20, 20)

print(City.distance(c1,c2))