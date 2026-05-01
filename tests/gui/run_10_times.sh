#!/bin/bash
mkdir -p report
for i in {1..10}
do
  echo "Running iteration $i..."
  npx playwright test rice.spec.ts --reporter=json > report/run-$i.json 2>&1
  if [ $? -eq 0 ]; then
    echo "Iteration $i passed."
  else
    echo "Iteration $i failed."
  fi
done
