import random

from distance import greatCircleDistance


class AntColonyOptimizer:
    """Solve a TSP over a list of places using the Ant System algorithm."""

    def __init__(self, places, nAnts=20, nIterations=100,
                 alpha=1.0, beta=3.0, evaporation=0.5, q=100.0):
        self.places = places
        self.n = len(places)            # number of places to visit

        # --- ACO hyper-parameters ---
        self.nAnts = nAnts              # number of ants released per iteration
        self.nIterations = nIterations  # number of iterations
        self.alpha = alpha              # weight of the pheromone trail
        self.beta = beta                # weight of the heuristic (closeness)
        self.evaporation = evaporation  # pheromone evaporation rate (rho)
        self.q = q                      # pheromone quantity dropped per tour

        # Distances never change, so we compute them only once (faster).
        self.distances = self._buildDistanceMatrix()

        # Every edge starts with the same small amount of pheromone (1.0).
        self.pheromones = [[1.0] * self.n for _ in range(self.n)]

    def _buildDistanceMatrix(self):
        """Return an n x n matrix with the distance between every pair of places."""
        matrix = [[0.0] * self.n for _ in range(self.n)]
        for i in range(self.n):
            for j in range(self.n):
                if i != j:  # distance from a place to itself stays 0
                    matrix[i][j] = greatCircleDistance(self.places[i], self.places[j])
        return matrix

    def _tourLength(self, tour):
        """Total distance of a closed tour (it returns to the start)."""
        total = 0.0
        for k in range(self.n):
            current = tour[k]
            # "% self.n" wraps around: after the last city we go back to
            # tour[0], which closes the loop (subject: come back to start).
            nextCity = tour[(k + 1) % self.n]
            total += self.distances[current][nextCity]
        return total

    def _chooseNext(self, current, visited):
        """
        Choose the next city for an ant located at `current`.

        Each unvisited city j receives a weight:
            weight(j) = pheromone(current, j)^alpha * (1 / distance)^beta
        Then a city is drawn at random, proportionally to those weights
        (roulette-wheel selection).
        """
        candidates = []
        weights = []
        for j in range(self.n):
            if j not in visited:
                # Pheromone term: "have other ants liked this edge?"
                tau = self.pheromones[current][j] ** self.alpha
                # Heuristic term: closer cities are more attractive.
                eta = (1.0 / self.distances[current][j]) ** self.beta
                candidates.append(j)
                weights.append(tau * eta)

        # Roulette wheel: pick a random point on the total weight, then walk
        # through the candidates until the cumulative sum passes it.
        total = sum(weights)
        target = random.uniform(0, total)
        cumulative = 0.0
        for city, weight in zip(candidates, weights):
            cumulative += weight
            if cumulative >= target:
                return city
        return candidates[-1]  # safety fallback (floating-point edge case)

    def _buildTour(self):
        """One ant builds a full tour, always starting from place 0."""
        start = 0
        tour = [start]
        visited = {start}  # a set guarantees a city is never visited twice
        while len(tour) < self.n:
            nextCity = self._chooseNext(tour[-1], visited)
            tour.append(nextCity)
            visited.add(nextCity)
        return tour
    
    def _updatePheromones(self, tours, lengths):
        """Evaporate the old pheromone, then let each ant deposit new pheromone."""
        # 1) Evaporation: every edge loses a fraction of its pheromone.
        #    This prevents the colony from getting stuck on an early solution.
        for i in range(self.n):
            for j in range(self.n):
                self.pheromones[i][j] *= (1 - self.evaporation)
        # 2) Deposit: a shorter tour deposits more pheromone (q / length),
        #    so good edges get reinforced more strongly.
        for tour, length in zip(tours, lengths):
            deposit = self.q / length
            for k in range(self.n):
                i = tour[k]
                j = tour[(k + 1) % self.n]
                self.pheromones[i][j] += deposit
                self.pheromones[j][i] += deposit  # symmetric: dist(i,j) = dist(j,i)

    def run(self):
        """Run the algorithm and return (bestTour, bestLength)."""
        bestTour = None
        bestLength = float("inf")
        for _ in range(self.nIterations):
            # Every ant builds its own tour, then we measure each one.
            tours = [self._buildTour() for _ in range(self.nAnts)]
            lengths = [self._tourLength(tour) for tour in tours]
            # Remember the best tour found so far.
            for tour, length in zip(tours, lengths):
                if length < bestLength:
                    bestLength = length
                    bestTour = tour
            # Reinforce good edges / fade bad ones for the next iteration.
            self._updatePheromones(tours, lengths)

        return bestTour, bestLength