FROM python:3.12-slim

WORKDIR /app

COPY TimeTrackerSystem/server.py ./

RUN mkdir -p /data /data/backup

ENV DATA_DIR=/data

EXPOSE 5000

CMD ["python", "server.py"]
