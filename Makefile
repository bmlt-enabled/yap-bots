.PHONY: simulate

simulate: redis
	ngrok http 3200

redis:
	docker run -d -p 6379:6379 redis
