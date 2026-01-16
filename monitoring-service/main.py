from fastapi import FastAPI, HTTPException
from dotenv import load_dotenv
import os
import redis
import json
import requests
import pymysql

load_dotenv()

app = FastAPI()

# Redis configuration
REDIS_HOST = os.getenv("REDIS_HOST", "localhost")
REDIS_PORT = int(os.getenv("REDIS_PORT", 6379))
REDIS_DB = 0  # Default Redis database
redis_client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, db=REDIS_DB, decode_responses=True)

# MySQL configuration for Submission Service data
MYSQL_HOST = os.getenv("MYSQL_HOST")
MYSQL_PORT = int(os.getenv("MYSQL_PORT", 3306))
MYSQL_USER = os.getenv("MYSQL_USER")
MYSQL_PASSWORD = os.getenv("MYSQL_PASSWORD")
MYSQL_DB = os.getenv("MYSQL_DB")

# Other service URLs
SUBMISSION_SERVICE_URL = os.getenv("SUBMISSION_SERVICE_URL")

@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "service": "monitoring-service"
    }

@app.get("/stats/global")
async def get_global_stats():
    cache_key = "global_stats"
    cached_stats = redis_client.get(cache_key)

    if cached_stats:
        return json.loads(cached_stats)

    try:
        # Fetch data from Submission Service (or directly from MySQL)
        # For simplicity, we'll connect directly to MySQL for submission data
        conn = pymysql.connect(
            host=MYSQL_HOST,
            port=MYSQL_PORT,
            user=MYSQL_USER,
            password=MYSQL_PASSWORD,
            database=MYSQL_DB
        )
        cursor = conn.cursor(pymysql.cursors.DictCursor)

        # Total submissions
        cursor.execute("SELECT COUNT(*) as total FROM submissions")
        total_submissions = cursor.fetchone()['total']

        # Submissions with status 'lulus'
        cursor.execute("SELECT COUNT(*) as graduated FROM submissions WHERE status = 'lulus'")
        graduated_submissions = cursor.fetchone()['graduated']
        
        # Calculate average completion time (dummy for now, requires more data/logic)
        # For a real implementation, this would involve tracking submission dates and milestone completion dates
        average_completion_time = "N/A" 

        # Calculate total progress percentage for all submissions
        cursor.execute("SELECT total_progress FROM submissions")
        all_progress = cursor.fetchall()
        total_progress_sum = sum([s['total_progress'] for s in all_progress])
        average_progress_percentage = (total_progress_sum / len(all_progress)) if len(all_progress) > 0 else 0


        stats = {
            "total_submissions": total_submissions,
            "graduated_submissions": graduated_submissions,
            "average_completion_time": average_completion_time,
            "average_progress_percentage": round(average_progress_percentage, 2)
        }

        redis_client.setex(cache_key, 60, json.dumps(stats)) # Cache for 60 seconds

        return stats

    except pymysql.Error as e:
        raise HTTPException(status_code=500, detail=f"Database error: {e}")
    except requests.exceptions.RequestException as e:
        raise HTTPException(status_code=500, detail=f"Error communicating with other services: {e}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"An unexpected error occurred: {e}")
    finally:
        if 'conn' in locals() and conn.open:
            conn.close()

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=int(os.getenv("PORT", 8084)))
