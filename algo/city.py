from math import sqrt
class City():
    def __init__(self, longitude: float, latitude: float):
        self.longitude = longitude
        self.latitude = latitude

    def __repr__(self):
        return f"City({self.longitude, self.latitude})"
    def distance(self, other):
        return sqrt((other.longitude-self.longitude)**2 + (other.latitude-self.latitude)**2)