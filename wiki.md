# Academy

## Data Structure

A general overview of the entity structure can be found here:
https://whimsical.com/youvo-academy-E89MFfEmpwiW8QgGqWwiZu


The academy introduces the content entities `Course`, `Lecture`, `Paragraph` and `Question`. The package is organised in respective modules where `academy` is the base module and `courses`, `lectures`, `paragraphs` and `quizzes` define the business logic of the entities above. Additionally, the module `child_entities` governs the relationship and inheritance of behavior between the different entities, i They follow a parent-/ child relationship where we have the following descendants:

- `Course` -> `Lecture` -> `Paragraph` -> `Question`
