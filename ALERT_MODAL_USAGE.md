# Alert Modal Usage Guide

This system includes a reusable alert modal component that can be used throughout the application. The modal supports different types: success, error, warning, info, and confirmation dialogs.

## Features

- ✅ Centered modal design
- ✅ Dark mode support
- ✅ Multiple alert types (success, error, warning, info, confirm)
- ✅ Promise-based API for confirmations
- ✅ Customizable button text
- ✅ Flowbite integration (with fallback)

## Basic Usage

### Success Alert
```javascript
showSuccessAlert('Success!', 'Your action was completed successfully.');
// or
alertSuccess('Success!', 'Your action was completed successfully.');
```

### Error Alert
```javascript
showErrorAlert('Error', 'Something went wrong. Please try again.');
// or
alertError('Error', 'Something went wrong. Please try again.');
```

### Warning Alert
```javascript
showWarningAlert('Warning', 'Please review your input before proceeding.');
// or
alertWarning('Warning', 'Please review your input before proceeding.');
```

### Info Alert
```javascript
showInfoAlert('Information', 'Here is some important information.');
// or
alertInfo('Information', 'Here is some important information.');
```

### Confirmation Dialog
```javascript
showConfirmAlert('Confirm Action', 'Are you sure you want to proceed?')
    .then((confirmed) => {
        if (confirmed) {
            // User clicked Confirm
            console.log('User confirmed');
        }
    })
    .catch((cancelled) => {
        // User clicked Cancel
        console.log('User cancelled');
    });

// or with async/await
async function handleDelete() {
    try {
        const confirmed = await showConfirmAlert('Delete Item', 'Are you sure you want to delete this item?');
        if (confirmed) {
            // Proceed with deletion
            deleteItem();
        }
    } catch (cancelled) {
        // User cancelled
        console.log('Deletion cancelled');
    }
}
```

## Advanced Usage

### Custom Button Text
```javascript
showConfirmAlert('Delete Item', 'This action cannot be undone.', {
    okText: 'Delete',
    cancelText: 'Keep'
});
```

### Using in PHP
```php
// In your PHP file
echo "<script>
    showSuccessAlert('Success', 'User created successfully!');
</script>";
```

### Using with Form Submissions
```javascript
document.getElementById('deleteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    showConfirmAlert('Delete User', 'Are you sure you want to delete this user?')
        .then((confirmed) => {
            if (confirmed) {
                this.submit();
            }
        });
});
```

## Available Functions

| Function | Alias | Description |
|----------|-------|-------------|
| `showAlert(type, title, message, options)` | - | Main function for showing alerts |
| `showSuccessAlert(title, message, options)` | `alertSuccess` | Show success alert |
| `showErrorAlert(title, message, options)` | `alertError` | Show error alert |
| `showWarningAlert(title, message, options)` | `alertWarning` | Show warning alert |
| `showInfoAlert(title, message, options)` | `alertInfo` | Show info alert |
| `showConfirmAlert(title, message, options)` | `alertConfirm` | Show confirmation dialog |

## Options Parameter

```javascript
{
    okText: 'OK',        // Text for OK/Confirm button
    cancelText: 'Cancel'  // Text for Cancel button (confirm dialogs only)
}
```

## Examples

### Example 1: Simple Success Message
```javascript
showSuccessAlert('Saved!', 'Your changes have been saved successfully.');
```

### Example 2: Error Handling
```javascript
fetch('/api/data')
    .then(response => response.json())
    .then(data => {
        showSuccessAlert('Success', 'Data loaded successfully!');
    })
    .catch(error => {
        showErrorAlert('Error', 'Failed to load data. Please try again.');
    });
```

### Example 3: Confirmation Before Action
```javascript
function deleteUser(userId) {
    showConfirmAlert('Delete User', 'Are you sure you want to delete this user? This action cannot be undone.')
        .then((confirmed) => {
            if (confirmed) {
                // Make API call to delete
                fetch(`/api/users/${userId}`, { method: 'DELETE' })
                    .then(() => {
                        showSuccessAlert('Deleted', 'User has been deleted successfully.');
                        // Refresh the list
                        location.reload();
                    })
                    .catch(() => {
                        showErrorAlert('Error', 'Failed to delete user.');
                    });
            }
        });
}
```

### Example 4: Form Validation
```javascript
document.getElementById('myForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Validate
    if (!formData.get('email')) {
        showErrorAlert('Validation Error', 'Please enter an email address.');
        return;
    }
    
    // Submit
    fetch('/api/submit', {
        method: 'POST',
        body: formData
    })
    .then(() => {
        showSuccessAlert('Success', 'Form submitted successfully!');
        this.reset();
    })
    .catch(() => {
        showErrorAlert('Error', 'Failed to submit form. Please try again.');
    });
}
```

## Notes

- The modal is automatically included in `includes/footer.php`
- The JavaScript file is loaded automatically
- All functions are available globally
- The modal works with or without Flowbite (has fallback)
- Dark mode is automatically supported

