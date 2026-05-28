from city import City
class Aco():
    def __init__(self, cityList: list):
        self.cityList = cityList

    def stockCity(self, city: City):
        self.cityList.append(city)
    
    def matriceDistance(self, cityList: list):
        nbCity = len(cityList)
        matDistance = []
        for i in range(nbCity):
            ligne = []
            for j in range(nbCity):
                if i == j:
                    ligne.append(0)
                else:
                    distance = cityList[i].distance(cityList[j])
                    ligne.append(distance)
            matDistance.append(ligne)
        return matDistance
    
c1 = City(10,13)
c2 = City(13, 10)

cityList = [c1,c2]
aco = Aco([]) 
print(aco.matriceDistance(cityList))