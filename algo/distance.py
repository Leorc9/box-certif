"""Geographic distance helpers: physical constants and great-circle distance."""
import math
# Physical constants, taken directly from the exam subject.
PI = 3.141592
EARTH_RADIUS_KM = 6378.197

def greatCircleDistance(placeA, placeB):
    """
    Great-circle distance (in km) between two Place objects.

    Implements the exact formula given in the subject:
        D = R * arccos( sin(latA)*sin(latB)
                        + cos(latA)*cos(latB)*cos(lonB - lonA) )
    with the coordinates expressed in radians.
    """
    # Step 1: convert decimal degrees to radians (sin/cos expect radians).
    latA = placeA.latitude * PI / 180
    lonA = placeA.longitude * PI / 180
    latB = placeB.latitude * PI / 180
    lonB = placeB.longitude * PI / 180
    # Step 2: spherical law of cosines (the subject's formula).
    cosine = (math.sin(latA) * math.sin(latB)
              + math.cos(latA) * math.cos(latB) * math.cos(lonB - lonA))
    # Step 3: clamp to [-1, 1]. Floating-point rounding can push the value
    # slightly above 1 or below -1, which would make math.acos crash.
    cosine = max(-1.0, min(1.0, cosine))
    # Step 4: multiply the angle by Earth's radius to get a distance in km.
    return EARTH_RADIUS_KM * math.acos(cosine)