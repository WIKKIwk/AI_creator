from __future__ import annotations
import time
from .http_server import start_http_server


def main():
    start_http_server(config_path=None)
    while True:
        time.sleep(3600)


if __name__ == "__main__":
    main()

