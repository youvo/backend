The following two patches provide custom behavior for the embedded question form which uses the 
multivalue form element.

The 'type' field is added to '#limit_validation_error' to ensure that the type form element is
transported through the form_state after adding a new element.

We modify the valueCallback because somehow the checkbox value is not retained correctly when
reordering the elements.

These are not robust implementations and should be reworked if the multivalue form element is used
elsewhere.
