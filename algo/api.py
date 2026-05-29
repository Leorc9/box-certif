"""FastAPI server — exposes the trip optimization algorithm as an HTTP API."""

import os
import sys

# Allow importing our algo modules from the same folder
sys.path.insert(0, os.path.dirname(__file__))

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

from aco import AntColonyOptimizer
from cluster import clusterCities
from place import Place

app = FastAPI()


# --- Request / Response models ---

class PlaceInput(BaseModel):
    name: str
    latitude: float
    longitude: float

class OptimizeRequest(BaseModel):
    places: list[PlaceInput]
    maxRadius: int = 150   # km — cities within this range share a hotel


# --- Endpoint ---

@app.post("/optimize")
def optimize(req: OptimizeRequest):
    if len(req.places) < 2:
        raise HTTPException(status_code=400, detail="At least 2 cities are required.")

    # Convert request data into Place objects used by the algo
    places = [Place(p.name, p.latitude, p.longitude) for p in req.places]

    # Step 1 — group nearby cities around one hotel city
    clusters = clusterCities(places, maxRadius=req.maxRadius)
    hotelCities = [c.hotelCity for c in clusters]

    # Step 2 — find the best route between hotel cities with ACO
    optimizer = AntColonyOptimizer(hotelCities, nAnts=20, nIterations=100)
    bestTour, bestLength = optimizer.run()

    dayTripDistance = sum(c.getDayTripDistance() for c in clusters)

    # Reorder clusters to match the optimized tour order
    hotelNameToCluster = {c.hotelCity.name: c for c in clusters}
    orderedClusters = [hotelNameToCluster[hotelCities[i].name] for i in bestTour]

    return {
        # Ordered list of hotel city names
        "route": [hotelCities[i].name for i in bestTour],
        # Clusters in optimized order — each hotel with its day-trip cities
        "clusters": [
            {
                "hotel": c.hotelCity.name,
                "dayTrips": [d.name for d in c.dayTripCities]
            }
            for c in orderedClusters
        ],
        "interHotelDistance": round(bestLength, 1),
        "dayTripDistance": round(dayTripDistance, 1),
        "totalDistance": round(bestLength + dayTripDistance, 1),
    }
