package pl.leroymerlin.expodywany038

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

class MainActivity : ComponentActivity() {
    private val viewModel = InventoryViewModel()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    ProductList(viewModel)
                }
            }
        }
    }
}

@Composable
fun ProductList(viewModel: InventoryViewModel) {
    val products by viewModel.products.collectAsState()

    Column(modifier = Modifier.padding(16.dp)) {
        Text(text = "Expo Dywany - Sklep 038", style = MaterialTheme.typography.headlineMedium)
        Spacer(modifier = Modifier.height(16.dp))
        
        if (products.isEmpty()) {
            Text("Brak produktów lub łączenie z bazą...")
        } else {
            LazyColumn {
                items(products) { product ->
                    Card(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Text(text = "Miejsce: ${product.location}${product.sub}", style = MaterialTheme.typography.titleMedium)
                            Text(text = "Nazwa: ${product.name}")
                            Text(text = "Ref: ${product.reference} | EAN: ${product.ean}")
                        }
                    }
                }
            }
        }
    }
}
