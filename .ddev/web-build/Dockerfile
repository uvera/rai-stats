# Adds libargon2 for the RaiffeisenClient's Argon2i login hash (via PHP FFI),
# since libsodium's high-level pwhash wrapper can't replicate the bank's raw
# Argon2i parameters (fixed 16-byte salt, 8 MiB minimum memory).
RUN apt-get update && apt-get install -y libargon2-1 libargon2-dev && rm -rf /var/lib/apt/lists/*
