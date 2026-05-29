"""The Place value object: a named geographic location."""


class Place:
    """A geographic location: a name plus decimal-degree coordinates."""

    def __init__(self, name, latitude, longitude):
        self.name = name            # display name, e.g. "Tokyo"
        self.latitude = latitude    # latitude in decimal degrees
        self.longitude = longitude  # longitude in decimal degrees

    def __repr__(self):
        # Lets us print a Place directly as its name.
        return self.name