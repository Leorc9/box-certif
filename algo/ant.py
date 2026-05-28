from random import choice
from city import City
class Ant:
    def __init__(self, start, visited, tour, currentCity):
        self.start = start
        self.visited = visited
        self.tour = tour
        self.currentCity = currentCity

    #turn ant at 0
    def reset(self):
        self.visited = [self.start]
        self.tour = [self.start]
        self.currentCity = self.start

    #random choice
    def selectNextCity(self, cityList):
        nextCityList = []
        for city in cityList:
            if city not in self.visited:
                nextCityList.append(city)
        return choice(nextCityList)
        
    #make a way
    def buildTour(self, cityList):
        self.reset()
        while len(self.visited) < len(cityList):
            nextCity = self.selectNextCity(cityList)
            self.visited.append(nextCity)
            self.tour.append(nextCity)
        self.tour.append(self.start)
        return self.tour

    #calculate total lenght
    def calculateLenght(self, cityList):
        sum = 0.0
        for city in range(len(cityList)-1):
            sum += City.distance(cityList[city], cityList[city+1])
        return sum
    
c1 = City(10,12)
c2 = City(9,10)
c3 = City(2,13)

cityList = [c1,c2,c3]
ant = Ant(c1, [], [], [])
print(ant.buildTour(cityList))
print (ant.calculateLenght(cityList))

